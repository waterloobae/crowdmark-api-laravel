<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkBookletPagesJsonJob;
use Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkOddPagesPdfJob;
use Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkPagesPdfJob;

Route::get('/crowdmark', function () {
    return view('crowdmark-api-laravel::crowdmark');
})->name('crowdmark');

Route::post('/crowdmark/save-booklet-pages-json', function (Request $request) {
    $assessmentIds = array_values(array_filter(
        array_map('trim', explode(',', $request->input('assessment_ids', '')))
    ));

    if (empty($assessmentIds)) {
        return response()->json(['error' => 'No assessment IDs provided.'], 422);
    }

    $forceRefresh = filter_var($request->input('force_refresh', false), FILTER_VALIDATE_BOOL);
    $jsonPath = trim((string) $request->input('json_path', ''));
    $jsonDisk = trim((string) $request->input('json_disk', ''));
    if ($jsonPath === '') {
        $jsonPath = null;
    }
    if ($jsonDisk === '') {
        $jsonDisk = null;
    }

    $token = \Illuminate\Support\Str::uuid()->toString();
    Cache::put("crowdmark_json_{$token}", ['status' => 'pending'], now()->addHours(24));

    GenerateCrowdmarkBookletPagesJsonJob::dispatch($token, $assessmentIds, $forceRefresh, $jsonPath, $jsonDisk);

    return response()->json(['token' => $token]);
})->name('crowdmark.save-booklet-pages-json');

Route::get('/crowdmark/json-status/{token}', function (string $token) {
    $raw = Cache::get("crowdmark_json_{$token}");

    if ($raw === null) {
        return response()->json(['status' => 'not_found'], 404);
    }

    if (is_string($raw)) {
        return response()->json(['status' => $raw]);
    }

    if (!is_array($raw)) {
        return response()->json(['status' => 'failed', 'error' => 'Invalid cache payload.']);
    }

    if (($raw['status'] ?? '') === 'done') {
        $cacheKey = (string) ($raw['cache_key'] ?? '');
        $savedPath = (string) ($raw['path'] ?? '');
        $downloadUrl = null;

        if ($savedPath !== '') {
            $downloadUrl = route('crowdmark.download-booklet-pages-json-by-token', ['token' => $token]);
        } elseif ($cacheKey !== '') {
            $downloadUrl = route('crowdmark.download-booklet-pages-json', ['cacheKey' => $cacheKey]);
        }

        return response()->json([
            'status' => 'done',
            'cache_key' => $cacheKey,
            'disk' => (string) ($raw['disk'] ?? 'local'),
            'count' => (int) ($raw['count'] ?? 0),
            'created_at' => (string) ($raw['created_at'] ?? ''),
            'download_url' => $downloadUrl,
        ]);
    }

    if (($raw['status'] ?? '') === 'failed') {
        return response()->json([
            'status' => 'failed',
            'error' => (string) ($raw['error'] ?? 'Unknown job failure.'),
        ]);
    }

    return response()->json(['status' => 'pending']);
})->name('crowdmark.json-status');

Route::get('/crowdmark/booklet-pages-json/{cacheKey}', function (string $cacheKey) {
    if (preg_match('/^[a-f0-9]{40}$/', $cacheKey) !== 1) {
        abort(404, 'Invalid cache key.');
    }

    $path = storage_path('app/crowdmark-cache/' . $cacheKey . '.json');
    if (!is_file($path)) {
        abort(404, 'JSON cache file not found.');
    }

    return response()->download(
        $path,
        'crowdmark_booklet_pages_' . $cacheKey . '.json',
        ['Content-Type' => 'application/json']
    );
})->name('crowdmark.download-booklet-pages-json');

Route::get('/crowdmark/booklet-pages-json-token/{token}', function (string $token) {
    $raw = Cache::get("crowdmark_json_{$token}");
    if (!is_array($raw) || ($raw['status'] ?? '') !== 'done') {
        abort(404, 'JSON cache file not ready.');
    }

    $path = (string) ($raw['path'] ?? '');
    $disk = trim((string) ($raw['disk'] ?? 'local'));
    if ($path === '') {
        abort(404, 'JSON cache file not found.');
    }

    $filename = basename($path);
    if ($filename === '') {
        $filename = 'crowdmark_booklet_pages.json';
    }

    // Backward compatibility for old cache payloads that stored absolute file paths only.
    if (is_file($path)) {
        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/json']
        );
    }

    if (!Storage::disk($disk)->exists($path)) {
        abort(404, 'JSON cache file not found.');
    }

    return response()->streamDownload(function () use ($disk, $path): void {
        $stream = Storage::disk($disk)->readStream($path);
        if ($stream === false) {
            throw new \RuntimeException('Failed to open JSON stream.');
        }

        fpassthru($stream);
        fclose($stream);
    }, $filename, ['Content-Type' => 'application/json']);
})->name('crowdmark.download-booklet-pages-json-by-token');

Route::post('/crowdmark/download-pages', function (Request $request) {
    $assessmentIds = array_values(array_filter(
        array_map('trim', explode(',', $request->input('assessment_ids', '')))
    ));
    $pageUuid = trim((string) $request->input('page_uuid', ''));
    $jsonPath = trim((string) $request->input('json_path', ''));
    $jsonDisk = trim((string) $request->input('json_disk', ''));
    $pdfSavePath = trim((string) $request->input('pdf_save_path', ''));
    $pdfDisk = trim((string) $request->input('pdf_disk', ''));

    if (empty($assessmentIds)) {
        return response()->json(['error' => 'No assessment IDs provided.'], 422);
    }

    if ($pageUuid === '') {
        return response()->json(['error' => 'No page UUID provided.'], 422);
    }

    if ($jsonPath === '') {
        $jsonPath = null;
    }
    if ($jsonDisk === '') {
        $jsonDisk = null;
    }
    if ($pdfSavePath === '') {
        $pdfSavePath = null;
    }
    if ($pdfDisk === '') {
        $pdfDisk = null;
    }

    $token = \Illuminate\Support\Str::uuid()->toString();
    Cache::put("crowdmark_pdf_{$token}", 'pending', now()->addHours(2));

    GenerateCrowdmarkPagesPdfJob::dispatch($token, $assessmentIds, $pageUuid, $jsonPath, $jsonDisk, $pdfSavePath, $pdfDisk);

    return response()->json(['token' => $token]);
})->name('crowdmark.download-pages');

Route::get('/crowdmark/pdf-status/{token}', function (string $token) {
    $raw = Cache::get("crowdmark_pdf_{$token}");

    if ($raw === null) {
        return response()->json(['status' => 'not_found'], 404);
    }

    if (str_starts_with($raw, 'failed:')) {
        return response()->json(['status' => 'failed', 'error' => substr($raw, 7)]);
    }

    return response()->json(['status' => $raw]);
})->name('crowdmark.pdf-status');

Route::get('/crowdmark/pdf-download/{token}', function (string $token) {
    $status = Cache::get("crowdmark_pdf_{$token}");

    if ($status !== 'done') {
        abort(404, 'PDF not ready.');
    }

    $path = (string) Cache::get("crowdmark_pdf_path_{$token}", "crowdmark-pdfs/{$token}.pdf");
    $disk = (string) Cache::get("crowdmark_pdf_disk_{$token}", 'local');

    if (!Storage::disk($disk)->exists($path)) {
        abort(404, 'PDF file missing.');
    }

    return response()->streamDownload(function () use ($disk, $path): void {
        $stream = Storage::disk($disk)->readStream($path);
        if ($stream === false) {
            throw new \RuntimeException('Failed to open PDF stream.');
        }

        fpassthru($stream);
        fclose($stream);
    }, basename($path) ?: "pages_{$token}.pdf", ['Content-Type' => 'application/pdf']);
})->name('crowdmark.pdf-download');

Route::post('/crowdmark/download-odd-pages', function (Request $request) {
    $assessmentIds = array_values(array_filter(
        array_map('trim', explode(',', $request->input('assessment_ids', '')))
    ));
    $maxPage = (int) $request->input('max_page', 39);
    $jsonPath = trim((string) $request->input('json_path', ''));
    $jsonDisk = trim((string) $request->input('json_disk', ''));
    $zipSavePath = trim((string) $request->input('zip_save_path', ''));
    $zipDisk = trim((string) $request->input('zip_disk', ''));

    if ($jsonPath === '') {
        $jsonPath = null;
    }
    if ($jsonDisk === '') {
        $jsonDisk = null;
    }
    if ($zipSavePath === '') {
        $zipSavePath = null;
    }
    if ($zipDisk === '') {
        $zipDisk = null;
    }

    if (empty($assessmentIds)) {
        return response()->json(['error' => 'No assessment IDs provided.'], 422);
    }

    $token = \Illuminate\Support\Str::uuid()->toString();
    Cache::put("crowdmark_pdf_{$token}", 'pending', now()->addHours(24));

    $requestingUserId = $request->user()?->getAuthIdentifier();
    GenerateCrowdmarkOddPagesPdfJob::dispatch($token, $assessmentIds, $maxPage, $jsonPath, $jsonDisk, $zipSavePath, $zipDisk, $requestingUserId !== null ? (string) $requestingUserId : null);

    return response()->json(['token' => $token]);
})->name('crowdmark.download-odd-pages');

Route::get('/crowdmark/zip-download/{token}', function (string $token) {
    $status = Cache::get("crowdmark_pdf_{$token}");

    if ($status === 'notice') {
        $message = (string) Cache::get("crowdmark_pdf_message_{$token}", 'ZIP job finished without generating a ZIP file.');
        abort(404, $message);
    }

    if ($status !== 'done') {
        abort(404, 'ZIP not ready.');
    }

    $path = (string) Cache::get("crowdmark_pdf_path_{$token}", "crowdmark-pdfs/{$token}.zip");
    $disk = (string) Cache::get("crowdmark_pdf_disk_{$token}", 'local');

    if (!Storage::disk($disk)->exists($path)) {
        abort(404, 'ZIP file missing.');
    }

    return response()->streamDownload(function () use ($disk, $path): void {
        $stream = Storage::disk($disk)->readStream($path);
        if ($stream === false) {
            throw new \RuntimeException('Failed to open ZIP stream.');
        }

        fpassthru($stream);
        fclose($stream);
    }, basename($path) ?: "odd_pages_{$token}.zip", ['Content-Type' => 'application/zip']);
})->name('crowdmark.zip-download');
