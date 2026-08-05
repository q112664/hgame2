@php
    $candidate = $snapshot['candidate'] ?? null;
    $active = $snapshot['active'] ?? null;
    $operations = $snapshot['operations'] ?? [];
    $formatBytes = static function (int $bytes): string {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KiB', 'MiB', 'GiB', 'TiB'];
        $value = $bytes;
        $unit = 'B';

        foreach ($units as $nextUnit) {
            $value /= 1024;
            $unit = $nextUnit;

            if ($value < 1024) {
                break;
            }
        }

        return number_format($value, 1).' '.$unit;
    };
@endphp

<div class="space-y-6">
    <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Current state</h3>
        </div>
        <dl class="grid gap-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Active disk</dt>
                <dd class="mt-1 font-mono text-sm font-medium text-gray-950 dark:text-white">
                    {{ $snapshot['disk'] ?? 'public' }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Candidate</dt>
                <dd class="mt-1 text-sm text-gray-950 dark:text-white">
                    {{ $candidate['bucket'] ?? 'Not configured' }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Connection test</dt>
                <dd class="mt-1">
                    @if ($candidate['tested'] ?? false)
                        <span class="inline-flex rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400">Passed</span>
                    @elseif ($candidate)
                        <span class="inline-flex rounded-md bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400">Required</span>
                    @else
                        <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Active R2 bucket</dt>
                <dd class="mt-1 text-sm text-gray-950 dark:text-white">
                    {{ $active['bucket'] ?? 'None' }}
                </dd>
            </div>
        </dl>

        @if (filled($candidate['test_error'] ?? null))
            <div class="border-t border-gray-200 px-6 py-4 dark:border-white/10">
                <p class="text-sm text-danger-700 dark:text-danger-400">{{ $candidate['test_error'] }}</p>
            </div>
        @endif
    </section>

    <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Recent operations</h3>
        </div>

        @if ($operations === [])
            <div class="px-6 py-8 text-sm text-gray-500 dark:text-gray-400">No media operations yet.</div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($operations as $operation)
                    @php
                        $statusColor = match ($operation['status']) {
                            'completed' => 'success',
                            'failed' => 'danger',
                            'running' => 'primary',
                            default => 'gray',
                        };
                    @endphp
                    <div class="px-6 py-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium capitalize text-gray-950 dark:text-white">{{ $operation['type'] }}</span>
                                    <span @class([
                                        'inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1',
                                        'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400' => $statusColor === 'success',
                                        'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400' => $statusColor === 'danger',
                                        'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400' => $statusColor === 'primary',
                                        'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300' => $statusColor === 'gray',
                                    ])>{{ $operation['status'] }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">#{{ $operation['id'] }}</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $operation['processed_items'] }}/{{ $operation['total_items'] }} processed,
                                    {{ $operation['succeeded_items'] }} succeeded,
                                    {{ $operation['skipped_items'] }} skipped,
                                    {{ $operation['failed_items'] }} failed
                                </p>
                            </div>

                            @if ($operation['failed_items'] > 0 && in_array($operation['status'], ['completed', 'failed'], true))
                                <x-filament::button
                                    wire:click="retryFailedOperation({{ $operation['id'] }})"
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-o-arrow-path"
                                >
                                    Retry failed
                                </x-filament::button>
                            @endif
                        </div>

                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-full bg-primary-500 transition-all" style="width: {{ $operation['progress'] }}%"></div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $formatBytes((int) $operation['source_bytes']) }} source</span>
                            <span>{{ $formatBytes((int) $operation['target_bytes']) }} target</span>
                            @if ($operation['started_at'])
                                <span>Started {{ $operation['started_at'] }}</span>
                            @endif
                            @if ($operation['completed_at'])
                                <span>Finished {{ $operation['completed_at'] }}</span>
                            @endif
                        </div>

                        @if (filled($operation['error'] ?? null))
                            <p class="mt-3 text-sm text-danger-700 dark:text-danger-400">{{ $operation['error'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
