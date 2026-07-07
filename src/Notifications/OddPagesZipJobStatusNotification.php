<?php

namespace Waterloobae\CrowdmarkApiLaravel\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OddPagesZipJobStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $status,
        private readonly string $message,
        private readonly array $assessmentIds = [],
        private readonly int $maxPage = 39,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'channel' => 'crowdmark',
            'job' => 'odd-pages-zip',
            'token' => $this->token,
            'status' => $this->status,
            'message' => $this->message,
            'assessment_ids' => $this->assessmentIds,
            'max_page' => $this->maxPage,
        ];
    }
}
