<?php

namespace Waterloobae\CrowdmarkApiLaravel\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Waterloobae\CrowdmarkApiLaravel\Crowdmark;

class GenerateCrowdmarkBookletPagesJsonJob implements ShouldQueue
{
    use Queueable;

    /**
     * This may require many API calls for large assessments.
     */
    public int $timeout = 0;

    public int $tries = 1;

    public function __construct(
        public readonly string $token,
        public readonly array $assessmentIds,
        public readonly bool $forceRefresh,
        public readonly ?string $jsonPath = null,
    ) {}

    public function handle(): void
    {
        $crowdmark = new Crowdmark();
        $saved = $crowdmark->saveBookletPageIndexJson($this->assessmentIds, $this->forceRefresh, $this->jsonPath);

        Cache::put("crowdmark_json_{$this->token}", [
            'status' => 'done',
            'cache_key' => $saved['cache_key'],
            'path' => $saved['path'],
            'count' => $saved['count'],
            'created_at' => $saved['created_at'],
        ], now()->addHours(24));
    }

    public function failed(\Throwable $e): void
    {
        Cache::put("crowdmark_json_{$this->token}", [
            'status' => 'failed',
            'error' => $e->getMessage(),
        ], now()->addHours(2));
    }
}
