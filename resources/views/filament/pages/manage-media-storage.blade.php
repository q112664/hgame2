@php
    $candidate = $snapshot['candidate'] ?? null;
    $active = $snapshot['active'] ?? null;
    $cleanup = $snapshot['cleanup'] ?? ['files' => 0, 'bytes' => 0];
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
    <x-filament::section
        heading="Current state"
        description="Live storage routing and the next verified maintenance action."
        icon="heroicon-o-circle-stack"
    >
        <div class="media-storage-state-grid -m-6 overflow-hidden rounded-b-xl">
            <div class="media-storage-state-item">
                <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Active disk</p>
                <div class="mt-2 flex items-center gap-2">
                    <span class="inline-flex size-2 rounded-full bg-success-500"></span>
                    <span class="font-mono text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $snapshot['disk'] ?? 'public' }}
                    </span>
                </div>
            </div>

            <div class="media-storage-state-item">
                <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">R2 candidate</p>
                <p class="mt-2 truncate text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $candidate['bucket'] ?? 'Not configured' }}
                </p>
            </div>

            <div class="media-storage-state-item">
                <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Connection</p>
                <div class="mt-2">
                    @if ($candidate['tested'] ?? false)
                        <x-filament::badge color="success" icon="heroicon-o-check-circle">Test passed</x-filament::badge>
                    @elseif ($candidate)
                        <x-filament::badge color="warning" icon="heroicon-o-exclamation-triangle">Test required</x-filament::badge>
                    @else
                        <x-filament::badge color="gray">Not available</x-filament::badge>
                    @endif
                </div>
            </div>

            <div class="media-storage-state-item">
                <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Active R2 bucket</p>
                <p class="mt-2 truncate text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $active['bucket'] ?? 'None' }}
                </p>
            </div>

            <div class="media-storage-state-item">
                <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Reclaimable originals</p>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $formatBytes((int) $cleanup['bytes']) }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $cleanup['files'] }} files
                    </span>
                </div>
            </div>
        </div>

        @if (filled($candidate['test_error'] ?? null))
            <div class="mt-4 rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400">
                {{ $candidate['test_error'] }}
            </div>
        @endif
    </x-filament::section>

    <x-filament::section
        heading="Recent operations"
        description="Queued media work, verification results, and storage impact."
        icon="heroicon-o-clock"
        compact
    >
        @if ($operations === [])
            <div class="px-6 py-10 text-center">
                <x-filament::icon icon="heroicon-o-inbox" class="mx-auto size-8 text-gray-400" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No media operations yet.</p>
            </div>
        @else
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($operations as $operation)
                    @php
                        $statusColor = match ($operation['status']) {
                            'completed' => 'success',
                            'failed' => 'danger',
                            'running' => 'info',
                            default => 'gray',
                        };
                        $operationLabel = match ($operation['type']) {
                            'migration' => 'R2 migration',
                            'validation' => 'R2 validation',
                            'optimization' => 'Image optimization',
                            'cleanup' => 'Original cleanup',
                            default => ucfirst($operation['type']),
                        };
                    @endphp

                    <article class="media-operation-row">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $operationLabel }}
                                </h4>
                                <x-filament::badge :color="$statusColor" size="sm">
                                    {{ ucfirst($operation['status']) }}
                                </x-filament::badge>
                                <span class="font-mono text-xs text-gray-400">#{{ $operation['id'] }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                @if ($operation['completed_at'])
                                    Finished {{ $operation['completed_at'] }}
                                @elseif ($operation['started_at'])
                                    Started {{ $operation['started_at'] }}
                                @endif
                            </p>
                        </div>

                        <div class="min-w-0">
                            <div class="flex items-center justify-between gap-4 text-xs">
                                <span class="font-medium text-gray-700 dark:text-gray-300">
                                    {{ $operation['processed_items'] }} of {{ $operation['total_items'] }} processed
                                </span>
                                <span class="font-mono text-gray-500 dark:text-gray-400">{{ $operation['progress'] }}%</span>
                            </div>
                            <div class="media-operation-progress mt-2" aria-label="Operation progress">
                                <div class="media-operation-progress-bar" style="width: {{ $operation['progress'] }}%"></div>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span class="text-success-600 dark:text-success-400">{{ $operation['succeeded_items'] }} succeeded</span>
                                <span>{{ $operation['skipped_items'] }} skipped</span>
                                <span @class(['text-danger-600 dark:text-danger-400' => $operation['failed_items'] > 0])>
                                    {{ $operation['failed_items'] }} failed
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4 lg:justify-end">
                            <dl class="grid grid-cols-2 gap-x-4 text-right text-xs">
                                <div>
                                    <dt class="text-gray-400">Source</dt>
                                    <dd class="mt-1 font-mono font-medium text-gray-700 dark:text-gray-300">
                                        {{ $formatBytes((int) $operation['source_bytes']) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-400">
                                        {{ $operation['type'] === 'cleanup' ? 'Reclaimed' : 'Saved' }}
                                    </dt>
                                    <dd class="mt-1 font-mono font-medium text-gray-700 dark:text-gray-300">
                                        {{ $formatBytes((int) $operation['storage_impact_bytes']) }}
                                    </dd>
                                </div>
                            </dl>

                            @if ($operation['failed_items'] > 0 && in_array($operation['status'], ['completed', 'failed'], true))
                                <x-filament::icon-button
                                    wire:click="retryFailedOperation({{ $operation['id'] }})"
                                    color="gray"
                                    icon="heroicon-o-arrow-path"
                                    label="Retry failed items"
                                    tooltip="Retry failed items"
                                />
                            @endif
                        </div>

                        @if (filled($operation['error'] ?? null))
                            <p class="rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:bg-danger-400/10 dark:text-danger-400 lg:col-span-3">
                                {{ $operation['error'] }}
                            </p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</div>
