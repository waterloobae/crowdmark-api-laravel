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
        public readonly string $pageUuid,
        public readonly ?string $jsonPath = null,
        public readonly ?string $jsonDisk = null,
        public readonly ?string $pdfSavePath = null,
        public readonly ?string $outputDisk = null,
    ) {}

    public function handle(): void
    {
        $crowdmark = new Crowdmark();
        $response = $crowdmark->downloadPagesByUuid($this->assessmentIds, $this->pageUuid, $this->jsonPath, $this->jsonDisk);

        $pdfContent = $response->getContent();
        $destinationRelativePath = $this->resolvePdfRelativePath($this->pdfSavePath);
        $disk = $this->resolveDiskName($this->outputDisk);

        Storage::disk($disk)->put($destinationRelativePath, $pdfContent);

        Cache::put("crowdmark_pdf_path_{$this->token}", $destinationRelativePath, now()->addHours(2));
        Cache::put("crowdmark_pdf_disk_{$this->token}", $disk, now()->addHours(2));
        Cache::put("crowdmark_pdf_{$this->token}", 'done', now()->addHours(2));
    }

    public function failed(\Throwable $e): void
    {
        Cache::put("crowdmark_pdf_{$this->token}", 'failed:' . $e->getMessage(), now()->addHours(2));
    }

    private function resolvePdfRelativePath(?string $pdfSavePath): string
    {
        if ($pdfSavePath === null || trim($pdfSavePath) === '') {
            return "crowdmark-pdfs/{$this->token}.pdf";
        }

        $normalized = str_replace('\\', '/', trim($pdfSavePath));
        $normalized = ltrim($normalized, '/');
        if ($normalized === '') {
            return "crowdmark-pdfs/{$this->token}.pdf";
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \InvalidArgumentException('Invalid PDF save path.');
            }
        }

        if (pathinfo($normalized, PATHINFO_EXTENSION) === '') {
            $normalized .= '.pdf';
        }

        return $normalized;
    }

    private function resolveDiskName(?string $disk): string
    {
        $normalized = trim((string) $disk);
        if ($normalized === '') {
            $normalized = 'local';
        }

        if (!is_array(config('filesystems.disks')) || !array_key_exists($normalized, config('filesystems.disks'))) {
            throw new \InvalidArgumentException('Invalid output disk: ' . $normalized);
        }

        return $normalized;
    }
}
