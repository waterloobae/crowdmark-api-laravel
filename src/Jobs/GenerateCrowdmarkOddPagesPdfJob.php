<?php

namespace Waterloobae\CrowdmarkApiLaravel\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Waterloobae\CrowdmarkApiLaravel\Crowdmark;

class GenerateCrowdmarkOddPagesPdfJob implements ShouldQueue
{
    use Queueable;

    /**
     * 0 = no timeout. This job fetches pages for thousands of booklets
     * and downloads tens of thousands of images - it will run for hours.
     */
    public int $timeout = 0;

    /** Fail immediately on first error - don't retry a multi-hour job automatically. */
    public int $tries = 1;

    public function __construct(
        public readonly string $token,
        public readonly array $assessmentIds,
        public readonly int $maxPage = 39,
        public readonly ?string $jsonPath = null,
        public readonly ?string $zipSavePath = null,
    ) {}

    public function handle(): void
    {
        $crowdmark = new Crowdmark();

        $zipPath = $crowdmark->generateOddPagesPdfZip($this->assessmentIds, $this->maxPage, $this->jsonPath);
        $destinationRelativePath = $this->resolveZipRelativePath($this->zipSavePath);
        $destinationAbsolutePath = Storage::path($destinationRelativePath);

        try {
            $destinationDir = dirname($destinationAbsolutePath);
            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
                throw new \RuntimeException('Unable to create destination directory for ZIP output.');
            }

            // Prefer rename to avoid copying bytes in PHP memory; fall back to copy across filesystems.
            if (!@rename($zipPath, $destinationAbsolutePath)) {
                if (!@copy($zipPath, $destinationAbsolutePath)) {
                    throw new \RuntimeException('Unable to persist generated ZIP to storage.');
                }

                @unlink($zipPath);
            }
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }

        Cache::put("crowdmark_pdf_path_{$this->token}", $destinationRelativePath, now()->addHours(24));
        Cache::put("crowdmark_pdf_{$this->token}", 'done', now()->addHours(24));
    }

    public function failed(\Throwable $e): void
    {
        Cache::put("crowdmark_pdf_{$this->token}", 'failed:' . $e->getMessage(), now()->addHours(2));
    }

    private function resolveZipRelativePath(?string $zipSavePath): string
    {
        if ($zipSavePath === null || trim($zipSavePath) === '') {
            return "crowdmark-pdfs/{$this->token}.zip";
        }

        $normalized = str_replace('\\', '/', trim($zipSavePath));
        $normalized = ltrim($normalized, '/');
        if ($normalized === '') {
            return "crowdmark-pdfs/{$this->token}.zip";
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \InvalidArgumentException('Invalid ZIP save path.');
            }
        }

        if (pathinfo($normalized, PATHINFO_EXTENSION) === '') {
            $normalized .= '.zip';
        }

        return $normalized;
    }
}
