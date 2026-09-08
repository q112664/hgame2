<?php

namespace App\Jobs;

use App\Support\IndexNow;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitIndexNowUrls implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public int $timeout = 20;

    public int $uniqueFor = 120;

    /**
     * @param  list<string>  $urls
     */
    public function __construct(public array $urls) {}

    public function uniqueId(): string
    {
        $urls = $this->urls;
        sort($urls);

        return hash('sha256', implode('|', $urls));
    }

    public function handle(): void
    {
        $key = IndexNow::normalizedKey();
        $host = IndexNow::host();
        $keyLocation = IndexNow::keyFileUrl();

        if (! IndexNow::enabled() || $key === null || $host === null || $keyLocation === null) {
            return;
        }

        $urls = array_values(array_unique(array_filter(
            $this->urls,
            fn (string $url): bool => $url !== '',
        )));

        if ($urls === []) {
            return;
        }

        $response = Http::timeout(8)
            ->connectTimeout(3)
            ->acceptJson()
            ->asJson()
            ->post(IndexNow::Endpoint, [
                'host' => $host,
                'key' => $key,
                'keyLocation' => $keyLocation,
                'urlList' => $urls,
            ]);

        if ($response->successful()) {
            return;
        }

        if ($response->serverError() || $response->status() === 429) {
            $response->throw();
        }

        Log::warning('IndexNow rejected the URL submission.', [
            'status' => $response->status(),
            'host' => $host,
            'url_count' => count($urls),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('IndexNow submission job failed.', [
            'url_count' => count($this->urls),
            'exception' => $exception->getMessage(),
        ]);
    }
}
