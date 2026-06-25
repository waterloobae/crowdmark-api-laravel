<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Save Booklet/Page JSON Cache</h2>
            <p class="mt-1 text-sm text-gray-600">Builds and caches booklet/page metadata for later download jobs.</p>

            <form id="json-cache-form" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="json_assessment_ids" class="mb-1 block text-sm font-medium text-gray-700">Assessment IDs (comma-separated)</label>
                    <textarea id="json_assessment_ids" name="assessment_ids" rows="3" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">euclid-z-french-student-form</textarea>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input id="json_force_refresh" name="force_refresh" type="checkbox" value="1" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    Force refresh from API
                </label>

                <div>
                    <label for="json_path" class="mb-1 block text-sm font-medium text-gray-700">Save path (optional, relative to storage/app)</label>
                    <input id="json_path" name="json_path" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="crowdmark-cache/custom/booklet-pages.json">
                </div>

                <button type="submit" id="json-submit-btn" class="fi-btn fi-btn-size-md fi-btn-color-primary fi-btn-outlined">Save JSON Cache</button>
            </form>

            <div id="json-status-box" class="mt-4 hidden text-sm">
                <p id="json-status-msg" class="text-gray-700">Saving JSON cache...</p>
                <a id="json-download-link" href="#" class="hidden text-primary-600 underline">Download JSON</a>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Download One Page by UUID</h2>
            <p class="mt-1 text-sm text-gray-600">Use a page UUID from the cached JSON to generate a single PDF.</p>

            <form id="pdf-form" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="assessment_ids" class="mb-1 block text-sm font-medium text-gray-700">Assessment IDs (comma-separated)</label>
                    <textarea id="assessment_ids" name="assessment_ids" rows="3" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">euclid-z-french-student-form</textarea>
                </div>

                <div>
                    <label for="page_uuid" class="mb-1 block text-sm font-medium text-gray-700">Page UUID</label>
                    <input id="page_uuid" name="page_uuid" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="page_id from cached JSON">
                </div>

                <div>
                    <label for="pdf_json_path" class="mb-1 block text-sm font-medium text-gray-700">Booklet/Page JSON path (optional)</label>
                    <input id="pdf_json_path" name="json_path" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="crowdmark-cache/custom/booklet-pages.json">
                </div>

                <button type="submit" id="submit-btn" class="fi-btn fi-btn-size-md fi-btn-color-primary fi-btn-outlined">Generate PDF</button>
            </form>

            <div id="status-box" class="mt-4 hidden text-sm">
                <p id="status-msg" class="text-gray-700">Queuing job...</p>
                <a id="download-link" href="#" class="hidden text-primary-600 underline">Download PDF</a>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Generate Odd Pages ZIP</h2>
            <p class="mt-1 text-sm text-gray-600">Builds booklet-based odd-page PDFs and bundles them in a ZIP.</p>

            <form id="zip-form" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="zip_assessment_ids" class="mb-1 block text-sm font-medium text-gray-700">Assessment IDs (comma-separated)</label>
                    <textarea id="zip_assessment_ids" name="assessment_ids" rows="3" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">euclid-z-french-student-form</textarea>
                </div>

                <div>
                    <label for="max_page" class="mb-1 block text-sm font-medium text-gray-700">Highest page number</label>
                    <input id="max_page" name="max_page" type="number" min="1" value="39" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                </div>

                <div>
                    <label for="zip_json_path" class="mb-1 block text-sm font-medium text-gray-700">Booklet/Page JSON path (optional)</label>
                    <input id="zip_json_path" name="json_path" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="crowdmark-cache/custom/booklet-pages.json">
                </div>

                <div>
                    <label for="zip_save_path" class="mb-1 block text-sm font-medium text-gray-700">ZIP save path (optional)</label>
                    <input id="zip_save_path" name="zip_save_path" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="crowdmark-pdfs/custom/odd-pages.zip">
                </div>

                <button type="submit" id="zip-submit-btn" class="fi-btn fi-btn-size-md fi-btn-color-primary fi-btn-outlined">Generate ZIP</button>
            </form>

            <div id="zip-status-box" class="mt-4 hidden text-sm">
                <p id="zip-status-msg" class="text-gray-700">Queuing job...</p>
                <a id="zip-download-link" href="#" class="hidden text-primary-600 underline">Download ZIP</a>
            </div>
        </div>
    </div>

    <script>
    (function () {
        function asUrlEncoded(formData) {
            return new URLSearchParams(formData);
        }

        function showError(element, message) {
            element.textContent = 'Error: ' + message;
        }

        async function parseJsonResponse(res) {
            const bodyText = await res.text();
            if (bodyText.trim() === '') {
                return {
                    ok: res.ok,
                    status: res.status,
                    data: {},
                    parseError: 'Empty response body',
                    raw: bodyText,
                };
            }

            try {
                return {
                    ok: res.ok,
                    status: res.status,
                    data: JSON.parse(bodyText),
                    parseError: null,
                    raw: bodyText,
                };
            } catch (_err) {
                return {
                    ok: res.ok,
                    status: res.status,
                    data: {},
                    parseError: 'Response is not valid JSON',
                    raw: bodyText,
                };
            }
        }

        function buildNonJsonError(result) {
            const preview = (result.raw ?? '').replace(/\s+/g, ' ').slice(0, 140);
            return result.parseError + ' (HTTP ' + result.status + '). Response starts with: ' + preview;
        }

        function poll(token, msgEl, linkEl, btnEl, statusBase, downloadBase) {
            const interval = setInterval(async function () {
                let pollRes;
                let pollParsed;
                try {
                    pollRes = await fetch(statusBase + token, { headers: { 'Accept': 'application/json' } });
                    pollParsed = await parseJsonResponse(pollRes);
                } catch (_err) {
                    return;
                }

                if (pollParsed.parseError) {
                    clearInterval(interval);
                    msgEl.textContent = buildNonJsonError(pollParsed);
                    btnEl.disabled = false;
                    return;
                }

                const pollData = pollParsed.data;

                if (pollData.status === 'done') {
                    clearInterval(interval);
                    msgEl.textContent = 'Ready!';
                    linkEl.href = downloadBase + token;
                    linkEl.classList.remove('hidden');
                    btnEl.disabled = false;
                } else if (pollData.status === 'failed') {
                    clearInterval(interval);
                    msgEl.textContent = 'Job failed: ' + (pollData.error ?? 'unknown reason');
                    btnEl.disabled = false;
                }
            }, 5000);
        }

        function pollJsonCache(token, msgEl, linkEl, btnEl) {
            const interval = setInterval(async function () {
                let pollRes;
                let pollParsed;
                try {
                    pollRes = await fetch('/crowdmark/json-status/' + token, { headers: { 'Accept': 'application/json' } });
                    pollParsed = await parseJsonResponse(pollRes);
                } catch (_err) {
                    return;
                }

                if (pollParsed.parseError) {
                    clearInterval(interval);
                    msgEl.textContent = buildNonJsonError(pollParsed);
                    btnEl.disabled = false;
                    return;
                }

                const pollData = pollParsed.data;

                if (pollData.status === 'done') {
                    clearInterval(interval);
                    msgEl.textContent = 'JSON saved. Rows: ' + (pollData.count ?? 0);
                    if (pollData.download_url) {
                        linkEl.href = pollData.download_url;
                        linkEl.classList.remove('hidden');
                    }
                    btnEl.disabled = false;
                } else if (pollData.status === 'failed') {
                    clearInterval(interval);
                    msgEl.textContent = 'JSON job failed: ' + (pollData.error ?? 'unknown reason');
                    btnEl.disabled = false;
                }
            }, 5000);
        }

        const jsonForm = document.getElementById('json-cache-form');
        const jsonStatusBox = document.getElementById('json-status-box');
        const jsonStatusMsg = document.getElementById('json-status-msg');
        const jsonDownloadLink = document.getElementById('json-download-link');
        const jsonSubmitBtn = document.getElementById('json-submit-btn');

        jsonForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            jsonDownloadLink.classList.add('hidden');
            jsonStatusMsg.textContent = 'Queuing JSON cache job...';
            jsonStatusBox.classList.remove('hidden');
            jsonSubmitBtn.disabled = true;

            const formData = new FormData(jsonForm);

            let res;
            let parsed;
            try {
                res = await fetch('{{ route('crowdmark.save-booklet-pages-json') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': formData.get('_token'),
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: asUrlEncoded(formData),
                });
                parsed = await parseJsonResponse(res);
            } catch (err) {
                showError(jsonStatusMsg, err.message);
                jsonSubmitBtn.disabled = false;
                return;
            }

            if (parsed.parseError) {
                showError(jsonStatusMsg, buildNonJsonError(parsed));
                jsonSubmitBtn.disabled = false;
                return;
            }

            const data = parsed.data;

            if (!parsed.ok || !data.token) {
                showError(jsonStatusMsg, data.error ?? 'Unknown error');
                jsonSubmitBtn.disabled = false;
                return;
            }

            jsonStatusMsg.textContent = 'Job queued - building booklet/page JSON in background...';
            pollJsonCache(data.token, jsonStatusMsg, jsonDownloadLink, jsonSubmitBtn);
        });

        const form = document.getElementById('pdf-form');
        const statusBox = document.getElementById('status-box');
        const statusMsg = document.getElementById('status-msg');
        const dlLink = document.getElementById('download-link');
        const submitBtn = document.getElementById('submit-btn');

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            dlLink.classList.add('hidden');
            statusMsg.textContent = 'Queuing job...';
            statusBox.classList.remove('hidden');
            submitBtn.disabled = true;

            const formData = new FormData(form);

            let res;
            let parsed;
            try {
                res = await fetch('{{ route('crowdmark.download-pages') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': formData.get('_token'),
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: asUrlEncoded(formData),
                });
                parsed = await parseJsonResponse(res);
            } catch (err) {
                showError(statusMsg, err.message);
                submitBtn.disabled = false;
                return;
            }

            if (parsed.parseError) {
                showError(statusMsg, buildNonJsonError(parsed));
                submitBtn.disabled = false;
                return;
            }

            const data = parsed.data;

            if (!parsed.ok || !data.token) {
                showError(statusMsg, data.error ?? 'Unknown error');
                submitBtn.disabled = false;
                return;
            }

            statusMsg.textContent = 'Job queued - waiting for worker...';
            poll(data.token, statusMsg, dlLink, submitBtn, '/crowdmark/pdf-status/', '/crowdmark/pdf-download/');
        });

        const zipForm = document.getElementById('zip-form');
        const zipStatusBox = document.getElementById('zip-status-box');
        const zipStatusMsg = document.getElementById('zip-status-msg');
        const zipDlLink = document.getElementById('zip-download-link');
        const zipSubmitBtn = document.getElementById('zip-submit-btn');

        zipForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            zipDlLink.classList.add('hidden');
            zipStatusMsg.textContent = 'Queuing job...';
            zipStatusBox.classList.remove('hidden');
            zipSubmitBtn.disabled = true;

            const formData = new FormData(zipForm);

            let res;
            let parsed;
            try {
                res = await fetch('{{ route('crowdmark.download-odd-pages') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': formData.get('_token'),
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: asUrlEncoded(formData),
                });
                parsed = await parseJsonResponse(res);
            } catch (err) {
                showError(zipStatusMsg, err.message);
                zipSubmitBtn.disabled = false;
                return;
            }

            if (parsed.parseError) {
                showError(zipStatusMsg, buildNonJsonError(parsed));
                zipSubmitBtn.disabled = false;
                return;
            }

            const data = parsed.data;

            if (!parsed.ok || !data.token) {
                showError(zipStatusMsg, data.error ?? 'Unknown error');
                zipSubmitBtn.disabled = false;
                return;
            }

            zipStatusMsg.textContent = 'Job queued - this can run for several hours.';
            poll(data.token, zipStatusMsg, zipDlLink, zipSubmitBtn, '/crowdmark/pdf-status/', '/crowdmark/zip-download/');
        });
    })();
    </script>
</x-filament-panels::page>
