<?php

namespace Waterloobae\CrowdmarkApiLaravel\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkBookletPagesJsonJob;
use Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkOddPagesPdfJob;
use Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkPagesPdfJob;
use UnitEnum;

class CrowdmarkExample extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static ?string $navigationLabel = 'Crowdmark Example';

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Crowdmark Example';

    protected static ?string $slug = 'crowdmark-example';

    protected string $view = 'crowdmark-api-laravel::filament.pages.crowdmark-example';

    public ?string $jsonToken = null;

    public ?string $pdfToken = null;

    public ?string $zipToken = null;

    public function queueJsonCacheAction(): Action
    {
        return Action::make('queueJsonCache')
            ->label('Queue JSON Cache')
            ->icon(Heroicon::OutlinedDocumentText)
            ->form([
                Textarea::make('assessment_ids')
                    ->label('Assessment IDs (comma-separated)')
                    ->default('euclid-z-french-student-form')
                    ->required(),
                Checkbox::make('force_refresh')
                    ->label('Force refresh from API')
                    ->default(false),
                TextInput::make('json_path')
                    ->label('Save path (optional, relative to storage/app)')
                    ->placeholder('crowdmark-cache/custom/booklet-pages.json'),
                TextInput::make('json_disk')
                    ->label('JSON disk (optional)')
                    ->placeholder('local'),
            ])
            ->action(function (array $data): void {
                $assessmentIds = $this->parseAssessmentIds((string) ($data['assessment_ids'] ?? ''));

                if ($assessmentIds === []) {
                    Notification::make()
                        ->danger()
                        ->title('No assessment IDs provided.')
                        ->send();

                    return;
                }

                $token = Str::uuid()->toString();
                $jsonPath = trim((string) ($data['json_path'] ?? '')) ?: null;
                $jsonDisk = trim((string) ($data['json_disk'] ?? '')) ?: null;
                $forceRefresh = (bool) ($data['force_refresh'] ?? false);

                Cache::put("crowdmark_json_{$token}", ['status' => 'pending'], now()->addHours(24));
                GenerateCrowdmarkBookletPagesJsonJob::dispatch($token, $assessmentIds, $forceRefresh, $jsonPath, $jsonDisk);

                $this->jsonToken = $token;

                Notification::make()
                    ->success()
                    ->title('JSON cache job queued.')
                    ->body("Token: {$token}")
                    ->send();
            });
    }

    public function checkJsonStatusAction(): Action
    {
        return Action::make('checkJsonStatus')
            ->label('Check JSON Status')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->form([
                TextInput::make('token')
                    ->label('JSON token')
                    ->default(fn (): ?string => $this->jsonToken)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $token = trim((string) $data['token']);
                $raw = Cache::get("crowdmark_json_{$token}");

                if ($raw === null) {
                    Notification::make()->danger()->title('Token not found.')->send();

                    return;
                }

                if (!is_array($raw)) {
                    Notification::make()->info()->title('Status: ' . (string) $raw)->send();

                    return;
                }

                $status = (string) ($raw['status'] ?? 'pending');
                $body = $status === 'done'
                    ? 'Rows: ' . (int) ($raw['count'] ?? 0)
                    : (string) ($raw['error'] ?? 'Still running.');

                Notification::make()
                    ->title('JSON status: ' . $status)
                    ->{in_array($status, ['done', 'pending'], true) ? 'success' : 'danger'}()
                    ->body($body)
                    ->send();
            });
    }

    public function queueSinglePagePdfAction(): Action
    {
        return Action::make('queueSinglePagePdf')
            ->label('Queue Single Page PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->form([
                Textarea::make('assessment_ids')
                    ->label('Assessment IDs (comma-separated)')
                    ->default('euclid-z-french-student-form')
                    ->required(),
                TextInput::make('page_uuid')
                    ->label('Page UUID')
                    ->required(),
                TextInput::make('json_path')
                    ->label('Booklet/Page JSON path (optional)')
                    ->placeholder('crowdmark-cache/custom/booklet-pages.json'),
                TextInput::make('json_disk')
                    ->label('JSON disk (optional)')
                    ->placeholder('local'),
                TextInput::make('pdf_save_path')
                    ->label('PDF save path (optional)')
                    ->placeholder('crowdmark-pdfs/custom/single-page.pdf'),
                TextInput::make('pdf_disk')
                    ->label('PDF disk (optional)')
                    ->placeholder('local'),
            ])
            ->action(function (array $data): void {
                $assessmentIds = $this->parseAssessmentIds((string) ($data['assessment_ids'] ?? ''));
                $pageUuid = trim((string) ($data['page_uuid'] ?? ''));

                if ($assessmentIds === [] || $pageUuid === '') {
                    Notification::make()->danger()->title('Assessment IDs and page UUID are required.')->send();

                    return;
                }

                $token = Str::uuid()->toString();
                $jsonPath = trim((string) ($data['json_path'] ?? '')) ?: null;
                $jsonDisk = trim((string) ($data['json_disk'] ?? '')) ?: null;
                $pdfSavePath = trim((string) ($data['pdf_save_path'] ?? '')) ?: null;
                $pdfDisk = trim((string) ($data['pdf_disk'] ?? '')) ?: null;

                Cache::put("crowdmark_pdf_{$token}", 'pending', now()->addHours(2));
                GenerateCrowdmarkPagesPdfJob::dispatch($token, $assessmentIds, $pageUuid, $jsonPath, $jsonDisk, $pdfSavePath, $pdfDisk);

                $this->pdfToken = $token;

                Notification::make()->success()->title('PDF job queued.')->body("Token: {$token}")->send();
            });
    }

    public function downloadSinglePagePdfAction(): Action
    {
        return Action::make('downloadSinglePagePdf')
            ->label('Download Single Page PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->form([
                TextInput::make('token')
                    ->label('PDF token')
                    ->default(fn (): ?string => $this->pdfToken)
                    ->required(),
            ])
            ->action(function (array $data) {
                $token = trim((string) $data['token']);
                $status = Cache::get("crowdmark_pdf_{$token}");

                if ($status !== 'done') {
                    Notification::make()->danger()->title('PDF is not ready yet.')->send();

                    return null;
                }

                $path = (string) Cache::get("crowdmark_pdf_path_{$token}", "crowdmark-pdfs/{$token}.pdf");
                $disk = (string) Cache::get("crowdmark_pdf_disk_{$token}", 'local');

                if (!Storage::disk($disk)->exists($path)) {
                    Notification::make()->danger()->title('PDF file missing.')->send();

                    return null;
                }

                return response()->streamDownload(function () use ($disk, $path): void {
                    $stream = Storage::disk($disk)->readStream($path);
                    if ($stream === false) {
                        throw new \RuntimeException('Failed to open PDF stream.');
                    }

                    fpassthru($stream);
                    fclose($stream);
                }, basename($path) ?: "pages_{$token}.pdf", ['Content-Type' => 'application/pdf']);
            });
    }

    public function queueOddPagesZipAction(): Action
    {
        return Action::make('queueOddPagesZip')
            ->label('Queue Odd Pages ZIP')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->form([
                Textarea::make('assessment_ids')
                    ->label('Assessment IDs (comma-separated)')
                    ->default('euclid-z-french-student-form')
                    ->required(),
                TextInput::make('max_page')
                    ->numeric()
                    ->default(39)
                    ->required(),
                TextInput::make('json_path')
                    ->label('Booklet/Page JSON path (optional)')
                    ->placeholder('crowdmark-cache/custom/booklet-pages.json'),
                TextInput::make('json_disk')
                    ->label('JSON disk (optional)')
                    ->placeholder('local'),
                TextInput::make('zip_save_path')
                    ->label('ZIP save path (optional)')
                    ->placeholder('crowdmark-pdfs/custom/odd-pages.zip'),
                TextInput::make('zip_disk')
                    ->label('ZIP disk (optional)')
                    ->placeholder('local'),
            ])
            ->action(function (array $data): void {
                $assessmentIds = $this->parseAssessmentIds((string) ($data['assessment_ids'] ?? ''));

                if ($assessmentIds === []) {
                    Notification::make()->danger()->title('No assessment IDs provided.')->send();

                    return;
                }

                $token = Str::uuid()->toString();
                $maxPage = max((int) ($data['max_page'] ?? 39), 1);
                $jsonPath = trim((string) ($data['json_path'] ?? '')) ?: null;
                $jsonDisk = trim((string) ($data['json_disk'] ?? '')) ?: null;
                $zipSavePath = trim((string) ($data['zip_save_path'] ?? '')) ?: null;
                $zipDisk = trim((string) ($data['zip_disk'] ?? '')) ?: null;

                Cache::put("crowdmark_pdf_{$token}", 'pending', now()->addHours(24));
                GenerateCrowdmarkOddPagesPdfJob::dispatch($token, $assessmentIds, $maxPage, $jsonPath, $jsonDisk, $zipSavePath, $zipDisk);

                $this->zipToken = $token;

                Notification::make()->success()->title('ZIP job queued.')->body("Token: {$token}")->send();
            });
    }

    public function downloadOddPagesZipAction(): Action
    {
        return Action::make('downloadOddPagesZip')
            ->label('Download Odd Pages ZIP')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->form([
                TextInput::make('token')
                    ->label('ZIP token')
                    ->default(fn (): ?string => $this->zipToken)
                    ->required(),
            ])
            ->action(function (array $data) {
                $token = trim((string) $data['token']);
                $status = Cache::get("crowdmark_pdf_{$token}");

                if ($status !== 'done') {
                    Notification::make()->danger()->title('ZIP is not ready yet.')->send();

                    return null;
                }

                $path = (string) Cache::get("crowdmark_pdf_path_{$token}", "crowdmark-pdfs/{$token}.zip");
                $disk = (string) Cache::get("crowdmark_pdf_disk_{$token}", 'local');

                if (!Storage::disk($disk)->exists($path)) {
                    Notification::make()->danger()->title('ZIP file missing.')->send();

                    return null;
                }

                return response()->streamDownload(function () use ($disk, $path): void {
                    $stream = Storage::disk($disk)->readStream($path);
                    if ($stream === false) {
                        throw new \RuntimeException('Failed to open ZIP stream.');
                    }

                    fpassthru($stream);
                    fclose($stream);
                }, basename($path) ?: "odd_pages_{$token}.zip", ['Content-Type' => 'application/zip']);
            });
    }

    /**
     * @return array<int, string>
     */
    private function parseAssessmentIds(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
