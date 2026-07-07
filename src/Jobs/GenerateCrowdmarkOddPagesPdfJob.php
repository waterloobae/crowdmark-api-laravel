<?php

namespace Waterloobae\CrowdmarkApiLaravel\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Waterloobae\CrowdmarkApiLaravel\Crowdmark;
use Waterloobae\CrowdmarkApiLaravel\Notifications\OddPagesZipJobStatusNotification;

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
        public readonly ?string $jsonDisk = null,
        public readonly ?string $zipSavePath = null,
        public readonly ?string $outputDisk = null,
        public readonly ?string $requestingUserId = null,
    ) {}

    public function handle(): void
    {
        $crowdmark = new Crowdmark();

        $zipPath = $crowdmark->generateOddPagesPdfZip($this->assessmentIds, $this->maxPage, $this->jsonPath, $this->jsonDisk);
        $destinationRelativePath = $this->resolveZipRelativePath($this->zipSavePath);
        $disk = $this->resolveDiskName($this->outputDisk);

        try {
            $stream = @fopen($zipPath, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open generated ZIP for persistence.');
            }

            try {
                if (!Storage::disk($disk)->put($destinationRelativePath, $stream)) {
                    throw new \RuntimeException('Unable to persist generated ZIP to storage.');
                }
            } finally {
                fclose($stream);
            }
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }

        Cache::put("crowdmark_pdf_path_{$this->token}", $destinationRelativePath, now()->addHours(24));
        Cache::put("crowdmark_pdf_disk_{$this->token}", $disk, now()->addHours(24));
        Cache::put("crowdmark_pdf_{$this->token}", 'done', now()->addHours(24));
    }

    public function failed(\Throwable $e): void
    {
        Cache::put("crowdmark_pdf_{$this->token}", 'failed:' . $e->getMessage(), now()->addHours(2));
        $this->notifyRequestingUser($e);
    }

    private function notifyRequestingUser(\Throwable $e): void
    {
        if ($this->requestingUserId === null || $this->requestingUserId === '') {
            return;
        }

        $modelClass = config('auth.providers.users.model');
        if (!is_string($modelClass) || $modelClass === '' || !class_exists($modelClass)) {
            return;
        }

        if (!method_exists($modelClass, 'query')) {
            return;
        }

        $user = $modelClass::query()->find($this->requestingUserId);
        if ($user === null || !method_exists($user, 'notify')) {
            return;
        }

        $status = $e instanceof \UnderflowException ? 'notice' : 'failed';
        $user->notify(new OddPagesZipJobStatusNotification(
            $this->token,
            $status,
            $e->getMessage(),
            $this->assessmentIds,
            $this->maxPage,
        ));
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
