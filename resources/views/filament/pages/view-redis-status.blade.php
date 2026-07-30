<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    Connection
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Live status for cache, queue, and session Redis usage.
                </p>
            </div>
            <x-filament::button wire:click="refreshStatus" color="gray" size="sm" icon="heroicon-o-arrow-path">
                Refresh
            </x-filament::button>
        </div>

        <div class="border-t border-gray-200 px-6 py-5 dark:border-white/10">
            @php($status = $this->status)

            <div class="mb-6 flex flex-wrap items-center gap-3">
                @if ($status['connected'] ?? false)
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-success-50 px-2.5 py-1 text-sm font-medium text-success-700 ring-1 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30">
                        <span class="size-1.5 rounded-full bg-success-500"></span>
                        Connected
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-danger-50 px-2.5 py-1 text-sm font-medium text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30">
                        <span class="size-1.5 rounded-full bg-danger-500"></span>
                        Disconnected
                    </span>
                @endif

                @if (filled($status['latencyMs'] ?? null))
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Ping {{ $status['latencyMs'] }} ms
                    </span>
                @endif
            </div>

            @if (filled($status['error'] ?? null))
                <div class="mb-6 rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400">
                    {{ $status['error'] }}
                </div>
            @endif

            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Host</dt>
                    <dd class="mt-1 font-mono text-sm text-gray-950 dark:text-white">
                        {{ $status['host'] ?? '—' }}:{{ $status['port'] ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Client</dt>
                    <dd class="mt-1 font-mono text-sm text-gray-950 dark:text-white">
                        {{ $status['client'] ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Redis version</dt>
                    <dd class="mt-1 font-mono text-sm text-gray-950 dark:text-white">
                        {{ $status['server']['redis_version'] ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Cache driver</dt>
                    <dd class="mt-1 font-mono text-sm text-gray-950 dark:text-white">
                        {{ $status['drivers']['cache'] ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Queue driver</dt>
                    <dd class="mt-1 font-mono text-sm text-gray-950 dark:text-white">
                        {{ $status['drivers']['queue'] ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Session driver</dt>
                    <dd class="mt-1 font-mono text-sm text-gray-950 dark:text-white">
                        {{ $status['drivers']['session'] ?? '—' }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    @if ($status['connected'] ?? false)
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Memory</h3>
                </div>
                <dl class="divide-y divide-gray-100 px-6 dark:divide-white/5">
                    @foreach ([
                        'used_memory_human' => 'Used memory',
                        'used_memory_peak_human' => 'Peak memory',
                        'maxmemory_human' => 'Max memory',
                    ] as $key => $label)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="font-mono text-sm text-gray-950 dark:text-white">
                                {{ $status['memory'][$key] ?? '—' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Stats</h3>
                </div>
                <dl class="divide-y divide-gray-100 px-6 dark:divide-white/5">
                    @foreach ([
                        'connected_clients' => 'Connected clients',
                        'instantaneous_ops_per_sec' => 'Ops / sec',
                        'total_commands_processed' => 'Commands processed',
                        'keyspace_hits' => 'Keyspace hits',
                        'keyspace_misses' => 'Keyspace misses',
                        'uptime_in_days' => 'Uptime (days)',
                        'db0' => 'DB0',
                    ] as $key => $label)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="font-mono text-sm text-gray-950 dark:text-white">
                                @if ($key === 'uptime_in_days')
                                    {{ $status['server']['uptime_in_days'] ?? '—' }}
                                @else
                                    {{ $status['stats'][$key] ?? '—' }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    @endif
</x-filament-panels::page>
