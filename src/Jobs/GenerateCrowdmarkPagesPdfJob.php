<?php

namespace Waterloobae\CrowdmarkApiLaravel\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Waterloobae\CrowdmarkApiLaravel\Crowdmark;

class GenerateCrowdmarkPagesPdfJob implements ShouldQueue
{
    use Queueable;

    /** @var int Abort the job if it runs longer than 10 minutes */
    public int $timeout = 600;

    public function __construct(
        public readonly string $token,
        public readonly array $assessmentIds,
        public readonly string $pageNumber,
    ) {}

    public function handle(): void
    {
        $crowdmark = new Crowdmark();
        $response = $crowdmark->downloadPagesByPageNumber($this->assessmentIds, $this->pageNumber);

        $pdfContent = $response->getContent();

        Storage::put("crowdmark-pdfs/{$this->token}.pdf", $pdfContent);

        Cache::put("crowdmark_pdf_{$this->token}", 'done', now()->addHours(2));
    }

    public function failed(\Throwable $e): void
    {
        Cache::put("crowdmark_pdf_{$this->token}", 'failed:' . $e->getMessage(), now()->addHours(2));
    }
}
