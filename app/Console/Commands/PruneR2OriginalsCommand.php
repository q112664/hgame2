<?php

namespace App\Console\Commands;

use App\Actions\Media\PruneVerifiedR2Originals;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

#[Signature('media:prune-r2-originals {--operation= : Cleanup operation id} {--force : Delete verified originals from R2}')]
#[Description('Delete unreferenced R2 originals whose optimized WebP still matches a completed cleanup record')]
class PruneR2OriginalsCommand extends Command
{
    public function handle(PruneVerifiedR2Originals $prune): int
    {
        $operation = $this->option('operation');
        $result = $prune(
            is_numeric($operation) ? (int) $operation : null,
            (bool) $this->option('force'),
        );

        $this->line('Cleanup operation #'.$result['operation_id']);
        $this->line('Eligible '.$result['eligible']);
        $this->line('Deletable '.$result['deletable']);
        $this->line('Deleted '.$result['deleted']);
        $this->line('Bytes '.$result['bytes'].' ('.Number::fileSize($result['bytes']).')');
        $this->line('Skipped '.$result['skipped']);

        foreach ($result['skip_reasons'] as $reason => $count) {
            $this->line('Skip '.$reason.' '.$count);
        }

        if ($result['skipped'] > 0) {
            $this->error('Some cleanup records were not safe to delete.');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            $this->info('Verified originals were deleted from R2.');
        } else {
            $this->comment('Dry run. Re-run with --force to delete the verified originals from R2.');
        }

        return self::SUCCESS;
    }
}
