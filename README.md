# Crowdmark Dashboard

## Install

```bash
composer require waterloobae/crowdmarkapilaravel
```

Set environment variables:

```env
CROWDMARK_API_KEY=your-secret-key
CROWDMARK_BASE_URL=https://app.crowdmark.com/
```

## Host Impact (Default Behavior)

This package is non-invasive by default:

- It does not auto-register web routes.
- It does not auto-register Filament pages.
- Service provider registration is optional.

If you want package namespaced views (for example `crowdmark-api-laravel::crowdmark` or the packaged Filament view), register the service provider manually in the host app:

```php
// bootstrap/providers.php

return [
    // ...
    Waterloobae\CrowdmarkApiLaravel\Providers\CrowdmarkApiLaravelServiceProvider::class,
];
```

If you only use package PHP classes/jobs and do not render package views, you can skip provider registration.

You can use `.env` only (plus optional host `services.php` fallback).

Optional `config/services.php` fallback:

```php
// config/services.php

return [
    // ... existing services

    'crowdmark' => [
        'base_url' => env('CROWDMARK_BASE_URL', 'https://app.crowdmark.com/'),
        'api_key' => env('CROWDMARK_API_KEY'),
    ],
];
```

## Queue Jobs Included In Package

These queue jobs are provided by the package and can be dispatched directly:

- `Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkBookletPagesJsonJob`
- `Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkPagesPdfJob`
- `Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkOddPagesPdfJob`

Run a worker for async operations:

```bash
php artisan queue:work --timeout=0 --tries=1
```

## Host App Integration Example (Routes Source)

`routes/web.php` is not part of this package runtime by default. Choose one of these opt-in approaches.

Approach A. Include the packaged route file directly:

```php
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(
    base_path('vendor/waterloobae/crowdmarkapilaravel/routes/web.php')
);
```

Approach B. Copy the route examples below into your host app and customize.

Shared imports:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkBookletPagesJsonJob;
use Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkPagesPdfJob;
use Waterloobae\CrowdmarkApiLaravel\Jobs\GenerateCrowdmarkOddPagesPdfJob;
```

Route 1. Crowdmark page entry point.

```php
Route::get('/crowdmark', function () {
    return view('crowdmark');
})->name('crowdmark');
```

Route 2. Queue booklet/page JSON cache build and return a polling token.

```php
Route::post('/crowdmark/save-booklet-pages-json', function (Request $request) {
    $assessmentIds = array_values(array_filter(
        array_map('trim', explode(',', $request->input('assessment_ids', '')))
    ));

    if (empty($assessmentIds)) {
        return response()->json(['error' => 'No assessment IDs provided.'], 422);
    }

    $forceRefresh = filter_var($request->input('force_refresh', false), FILTER_VALIDATE_BOOL);
    $jsonPath = trim((string) $request->input('json_path', '')) ?: null;

    $token = \Illuminate\Support\Str::uuid()->toString();
    Cache::put("crowdmark_json_{$token}", ['status' => 'pending'], now()->addHours(24));

    GenerateCrowdmarkBookletPagesJsonJob::dispatch($token, $assessmentIds, $forceRefresh, $jsonPath);

    return response()->json(['token' => $token]);
})->name('crowdmark.save-booklet-pages-json');
```

Route 3. Check JSON cache job status and return download URL when done.

```php
Route::get('/crowdmark/json-status/{token}', function (string $token) {
    $raw = Cache::get("crowdmark_json_{$token}");

    if ($raw === null) {
        return response()->json(['status' => 'not_found'], 404);
    }

    if (($raw['status'] ?? '') === 'done') {
        $cacheKey = (string) ($raw['cache_key'] ?? '');
        $savedPath = (string) ($raw['path'] ?? '');

        return response()->json([
            'status' => 'done',
            'cache_key' => $cacheKey,
            'count' => (int) ($raw['count'] ?? 0),
            'created_at' => (string) ($raw['created_at'] ?? ''),
            'download_url' => $savedPath !== ''
                ? route('crowdmark.download-booklet-pages-json-by-token', ['token' => $token])
                : route('crowdmark.download-booklet-pages-json', ['cacheKey' => $cacheKey]),
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
```

Route 4. Download JSON cache by hash key (default path).

```php
Route::get('/crowdmark/booklet-pages-json/{cacheKey}', function (string $cacheKey) {
    $path = storage_path('app/crowdmark-cache/' . $cacheKey . '.json');
    if (!is_file($path)) {
        abort(404, 'JSON cache file not found.');
    }

    return response()->download($path, 'crowdmark_booklet_pages_' . $cacheKey . '.json', [
        'Content-Type' => 'application/json',
    ]);
})->name('crowdmark.download-booklet-pages-json');
```

Route 5. Download JSON cache by token (custom json_path support).

```php
Route::get('/crowdmark/booklet-pages-json-token/{token}', function (string $token) {
    $raw = Cache::get("crowdmark_json_{$token}");
    $path = (string) ($raw['path'] ?? '');

    if (!is_array($raw) || ($raw['status'] ?? '') !== 'done' || $path === '' || !is_file($path)) {
        abort(404, 'JSON cache file not ready.');
    }

    return response()->download($path, basename($path) ?: 'crowdmark_booklet_pages.json', [
        'Content-Type' => 'application/json',
    ]);
})->name('crowdmark.download-booklet-pages-json-by-token');
```

Route 6. Queue single-page PDF generation for all selected assessments.

```php
Route::post('/crowdmark/download-pages', function (Request $request) {
    $assessmentIds = array_values(array_filter(
        array_map('trim', explode(',', $request->input('assessment_ids', '')))
    ));

    if (empty($assessmentIds)) {
        return response()->json(['error' => 'No assessment IDs provided.'], 422);
    }

    $token = \Illuminate\Support\Str::uuid()->toString();
    Cache::put("crowdmark_pdf_{$token}", 'pending', now()->addHours(2));

    GenerateCrowdmarkPagesPdfJob::dispatch($token, $assessmentIds, (string) $request->input('page', '1'));

    return response()->json(['token' => $token]);
})->name('crowdmark.download-pages');
```

Route 7. Poll single-page PDF job status.

```php
Route::get('/crowdmark/pdf-status/{token}', function (string $token) {
    $raw = Cache::get("crowdmark_pdf_{$token}");

    if ($raw === null) {
        return response()->json(['status' => 'not_found'], 404);
    }

    if (str_starts_with((string) $raw, 'failed:')) {
        return response()->json(['status' => 'failed', 'error' => substr((string) $raw, 7)]);
    }

    return response()->json(['status' => $raw]);
})->name('crowdmark.pdf-status');
```

Route 8. Download generated single-page PDF.

```php
Route::get('/crowdmark/pdf-download/{token}', function (string $token) {
    $status = Cache::get("crowdmark_pdf_{$token}");
    if ($status !== 'done') {
        abort(404, 'PDF not ready.');
    }

    $path = "crowdmark-pdfs/{$token}.pdf";
    if (!Storage::exists($path)) {
        abort(404, 'PDF file missing.');
    }

    return response()->download(Storage::path($path), "pages_{$token}.pdf", [
        'Content-Type' => 'application/pdf',
    ]);
})->name('crowdmark.pdf-download');
```

Route 9. Queue odd-pages ZIP generation (supports baseline json_path + zip_save_path).

```php
Route::post('/crowdmark/download-odd-pages', function (Request $request) {
    $assessmentIds = array_values(array_filter(
        array_map('trim', explode(',', $request->input('assessment_ids', '')))
    ));

    if (empty($assessmentIds)) {
        return response()->json(['error' => 'No assessment IDs provided.'], 422);
    }

    $token = \Illuminate\Support\Str::uuid()->toString();
    Cache::put("crowdmark_pdf_{$token}", 'pending', now()->addHours(24));

    GenerateCrowdmarkOddPagesPdfJob::dispatch(
        $token,
        $assessmentIds,
        (int) $request->input('max_page', 39),
        trim((string) $request->input('json_path', '')) ?: null,
        trim((string) $request->input('zip_save_path', '')) ?: null,
    );

    return response()->json(['token' => $token]);
})->name('crowdmark.download-odd-pages');
```

Route 10. Download generated odd-pages ZIP.

```php
Route::get('/crowdmark/zip-download/{token}', function (string $token) {
    $status = Cache::get("crowdmark_pdf_{$token}");
    if ($status !== 'done') {
        abort(404, 'ZIP not ready.');
    }

    $path = (string) Cache::get("crowdmark_pdf_path_{$token}", "crowdmark-pdfs/{$token}.zip");
    if (!Storage::exists($path)) {
        abort(404, 'ZIP file missing.');
    }

    return response()->download(Storage::path($path), basename($path) ?: "odd_pages_{$token}.zip", [
        'Content-Type' => 'application/zip',
    ]);
})->name('crowdmark.zip-download');
```

## Host App Integration Example (Blade Source)

`resources/views/crowdmark.blade.php` is not part of this package. Example form snippets:

```blade
<h2>Save Booklet/Page JSON Cache</h2>
<form id="json-cache-form">
    @csrf
    <textarea name="assessment_ids" rows="3" cols="60"></textarea>

    <label>
        <input name="force_refresh" type="checkbox" value="1">
        Force refresh from API
    </label>

    <input name="json_path" type="text" placeholder="crowdmark-cache/custom/booklet-pages.json">
    <button type="submit">Save JSON Cache</button>
</form>

<h2>All odd pages - ZIP of booklet-based PDFs</h2>
<form id="zip-form">
    @csrf
    <textarea name="assessment_ids" rows="3" cols="60"></textarea>
    <input name="max_page" type="number" min="1" value="39">
    <input name="json_path" type="text" placeholder="crowdmark-cache/custom/booklet-pages.json">
    <input name="zip_save_path" type="text" placeholder="crowdmark-pdfs/custom/odd-pages.zip">
    <button type="submit">Generate ZIP</button>
</form>
```

Async submit/poll pattern (example):

```html
<script>
async function postForm(url, form) {
    const formData = new FormData(form);
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': formData.get('_token'),
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(formData),
    });
    return res.json();
}

async function pollStatus(statusUrl, onDone) {
    const timer = setInterval(async () => {
        const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (data.status === 'done') {
            clearInterval(timer);
            onDone(data);
        }
        if (data.status === 'failed') {
            clearInterval(timer);
            console.error(data.error ?? 'Job failed');
        }
    }, 5000);
}
</script>
```

## Filament Integration (Optional)

The package includes a Filament page class:

- `Waterloobae\CrowdmarkApiLaravel\Filament\Pages\CrowdmarkExample`

Register it explicitly in your panel provider:

```php
use Filament\Pages\Dashboard;
use Waterloobae\CrowdmarkApiLaravel\Filament\Pages\CrowdmarkExample;

// in panel() chain
->pages([
    Dashboard::class,
    CrowdmarkExample::class,
])
```

If this page uses package namespaced views, keep the package service provider registered in `bootstrap/providers.php`.

## Odd-pages Incremental Behavior

Odd-pages ZIP uses JSON as baseline and only downloads changed/new odd pages by checking:

- `assessment_id`
- `booklet_id`
- `page_number`
- `updated_at`

After a successful ZIP run with downloaded pages, JSON cache is refreshed at `json_path` (or default path).

Index timestamps are derived from API page timestamps, not system datetime.

