@php
    $candidate = $snapshot['candidate'] ?? null;
    $active = $snapshot['active'] ?? null;
    $usingR2 = ($snapshot['disk'] ?? 'public') === 'r2';
    $pending = $candidate !== null && $active !== null && ($candidate['id'] ?? null) !== ($active['id'] ?? null);
@endphp

<div class="space-y-6">
    <x-filament::section
        heading="当前存储"
        description="站点正在使用的位置和连接状态。"
        icon="heroicon-o-circle-stack"
    >
        <div class="media-storage-state-grid -m-6 overflow-hidden rounded-b-xl">
            <div class="media-storage-state-item">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">存储位置</p>
                <div class="mt-2 flex items-center gap-2">
                    <span @class([
                        'inline-flex size-2 rounded-full',
                        'bg-success-500' => $usingR2,
                        'bg-gray-400' => ! $usingR2,
                    ])></span>
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $usingR2 ? 'Cloudflare R2' : '本地磁盘' }}
                    </span>
                </div>
                @if (filled($active['activated_at'] ?? null))
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        启用于 {{ $active['activated_at'] }}
                    </p>
                @endif
            </div>

            <div class="media-storage-state-item">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">公开域名</p>
                <p class="mt-2 truncate text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $active['public_url'] ?? '未启用' }}
                </p>
            </div>

            <div class="media-storage-state-item">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">存储桶</p>
                <p class="mt-2 truncate text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $active['bucket'] ?? '未启用' }}
                </p>
            </div>

            <div class="media-storage-state-item">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">连接</p>
                <div class="mt-2">
                    @if ($usingR2 && ($active['tested'] ?? false))
                        <x-filament::badge color="success" icon="heroicon-o-check-circle">测试通过</x-filament::badge>
                    @elseif ($usingR2)
                        <x-filament::badge color="warning" icon="heroicon-o-exclamation-triangle">需要测试</x-filament::badge>
                    @else
                        <x-filament::badge color="gray">未启用</x-filament::badge>
                    @endif
                </div>
                @if ($usingR2 && filled($active['tested_at'] ?? null))
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ $active['tested_at'] }}
                    </p>
                @endif
            </div>
        </div>

        @if ($pending)
            <div class="mt-4 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400">
                @if ($candidate['tested'] ?? false)
                    有一份已保存、尚未应用的配置。连接测试已通过，可以使用「应用配置」切换。当前站点仍使用 {{ $active['public_url'] }}。
                @else
                    有一份已保存、尚未应用的配置。请先测试连接，通过后再应用。当前站点仍使用 {{ $active['public_url'] }}。
                @endif
            </div>
        @elseif ($candidate)
            <div class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                已保存一份 R2 配置，站点仍使用本地磁盘。测试连接只检查能否读写，不会切换存储。
            </div>
        @endif

        @if (($snapshot['restore']['status'] ?? null) === 'running')
            <div wire:poll.5s class="mt-4 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400">
                正在从 R2 复制到本地：{{ $snapshot['restore']['processed'] }}/{{ $snapshot['restore']['total'] }}
                @if (($snapshot['restore']['failed'] ?? 0) > 0)
                    ，失败 {{ $snapshot['restore']['failed'] }}
                @endif
            </div>
        @elseif (filled($snapshot['restore']['error'] ?? null))
            <div class="mt-4 rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400">
                {{ $snapshot['restore']['error'] }}
            </div>
        @endif

        @if (filled($candidate['test_error'] ?? null))
            <div class="mt-4 rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400">
                {{ $candidate['test_error'] }}
            </div>
        @endif
    </x-filament::section>
</div>
