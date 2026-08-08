<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMediaOperationItem;
use App\Models\MediaOperation;
use App\Models\MediaOperationItem;
use App\Support\MediaStorageManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecoverMediaOperations extends Command
{
    protected $signature = 'media:recover-operations
        {--dry-run : Report stale items without changing or dispatching them}';

    protected $description = 'Recover stale media operation items and redispatch pending work';

    public function handle(MediaStorageManager $manager): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $recovered = 0;
        $failed = 0;
        $legacyStaleBefore = now()->subMinutes(5);

        MediaOperationItem::query()
            ->where('status', MediaOperationItem::StatusRunning)
            ->where(function (Builder $query) use ($legacyStaleBefore): void {
                $query->where(function (Builder $leased): void {
                    $leased->whereNotNull('lease_expires_at')
                        ->where('lease_expires_at', '<=', now());
                })->orWhere(function (Builder $legacy) use ($legacyStaleBefore): void {
                    $legacy->whereNull('lease_expires_at')
                        ->where('updated_at', '<=', $legacyStaleBefore);
                });
            })
            ->whereHas('operation', fn ($query) => $query->whereIn('status', [
                MediaOperation::StatusPending,
                MediaOperation::StatusRunning,
            ]))
            ->orderBy('id')
            ->chunkById(100, function ($items) use (&$recovered, &$failed, $dryRun, $legacyStaleBefore): void {
                foreach ($items as $candidate) {
                    if ($dryRun) {
                        $recovered++;

                        continue;
                    }

                    $result = DB::transaction(function () use ($candidate, $legacyStaleBefore): string {
                        $item = MediaOperationItem::query()
                            ->lockForUpdate()
                            ->find((int) $candidate->getKey());

                        if ($item === null || $item->status !== MediaOperationItem::StatusRunning) {
                            return 'ignored';
                        }

                        if ($item->lease_expires_at?->isAfter(now()) === true) {
                            return 'ignored';
                        }

                        if ($item->lease_expires_at === null && $item->updated_at->isAfter($legacyStaleBefore)) {
                            return 'ignored';
                        }

                        if ($item->attempts >= ProcessMediaOperationItem::MaxAttempts) {
                            $item->forceFill([
                                'status' => MediaOperationItem::StatusFailed,
                                'error' => 'The item lease expired after the maximum queue attempts.',
                                'completed_at' => now(),
                                'lease_token' => null,
                                'lease_expires_at' => null,
                                'heartbeat_at' => now(),
                            ])->save();

                            return 'failed';
                        }

                        // Replace the token so a late delivery from the old
                        // worker cannot claim the recovered item.
                        $item->forceFill([
                            'status' => MediaOperationItem::StatusPending,
                            'dispatch_token' => (string) Str::uuid(),
                            'dispatched_at' => null,
                            'lease_token' => null,
                            'lease_expires_at' => null,
                            'heartbeat_at' => now(),
                            'error' => 'Recovered after an expired worker lease.',
                            'completed_at' => null,
                        ])->save();

                        return 'recovered';
                    });

                    match ($result) {
                        'recovered' => $recovered++,
                        'failed' => $failed++,
                        default => null,
                    };
                }
            });

        if (! $dryRun) {
            MediaOperation::query()
                ->whereIn('status', [
                    MediaOperation::StatusPending,
                    MediaOperation::StatusRunning,
                ])
                ->orderBy('id')
                ->each(function (MediaOperation $operation) use ($manager): void {
                    $manager->refreshOperationProgress($operation);
                    $manager->dispatchPending($operation->refresh());
                });
        }

        $this->info(($dryRun ? 'Would recover ' : 'Recovered ').$recovered.' item(s).');

        if ($failed > 0) {
            $this->warn("Marked {$failed} item(s) as failed after exhausted attempts.");
        }

        return self::SUCCESS;
    }
}
