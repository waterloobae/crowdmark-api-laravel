<h1>Download Crowdmark Pages</h1>

{{-- -- Build/reuse booklet-page JSON cache -- --}}
<h2>Save Booklet/Page JSON Cache</h2>
<p><small>This saves booklet id, booklet number, page number, page UUID, and page self link to JSON so later jobs can reuse it.</small></p>
<form id="json-cache-form">
    @csrf
    <div>
        <label for="json_assessment_ids">Assessment IDs <small>(comma-separated)</small></label><br>
        <textarea id="json_assessment_ids" name="assessment_ids" rows="3" cols="60"
            placeholder="euclid-z-french-student-form, ...">euclid-z-french-student-form</textarea>
    </div>
    <div>
        <label>
            <input id="json_force_refresh" name="force_refresh" type="checkbox" value="1">
            Force refresh from API (ignore existing JSON)
        </label>
    </div>
    <div>
        <label for="json_path">Save path <small>(optional, relative to storage/app)</small></label><br>
        <input id="json_path" name="json_path" type="text" size="60" placeholder="crowdmark-cache/custom/booklet-pages.json">
    </div>
    <div>
        <label for="json_disk">JSON disk <small>(optional, default: local)</small></label><br>
        <input id="json_disk" name="json_disk" type="text" size="60" placeholder="local">
    </div>
    <br>
    <button type="submit" id="json-submit-btn">Save JSON Cache</button>
</form>

<div id="json-status-box" style="margin-top:1rem; display:none;">
    <p id="json-status-msg">Saving JSON cache...</p>
    <a id="json-download-link" href="#" style="display:none; font-weight:bold;">Download JSON</a>
</div>

{{-- -- Single page download -- --}}
<hr style="margin:2rem 0;">
<h2>Download one page by UUID</h2>
<p><small>Use the page UUID from the cached booklet/page JSON file. It can come from <code>page_id</code> or be derived from the tail of <code>self_link</code> (for older caches).</small></p>
<form id="pdf-form">
    @csrf
    <div>
        <label for="assessment_ids">Assessment IDs <small>(comma-separated)</small></label><br>
        <textarea id="assessment_ids" name="assessment_ids" rows="3" cols="60"
            placeholder="euclid-z-french-student-form, ...">euclid-z-french-student-form</textarea>
    </div>
    <div>
        <label for="page_uuid">Page UUID</label><br>
        <input id="page_uuid" name="page_uuid" type="text" size="60" placeholder="page_id from cached JSON">
    </div>
    <div>
        <label for="json_path">Booklet/Page JSON path <small>(optional, relative to storage/app)</small></label><br>
        <input id="json_path" name="json_path" type="text" size="60" placeholder="crowdmark-cache/custom/booklet-pages.json">
    </div>
    <div>
        <label for="single_json_disk">JSON disk <small>(optional, default: local)</small></label><br>
        <input id="single_json_disk" name="json_disk" type="text" size="60" placeholder="local">
    </div>
    <div>
        <label for="pdf_save_path">PDF save path <small>(optional, relative to storage/app)</small></label><br>
        <input id="pdf_save_path" name="pdf_save_path" type="text" size="60" placeholder="crowdmark-pdfs/custom/single-page.pdf">
    </div>
    <div>
        <label for="pdf_disk">PDF disk <small>(optional, default: local)</small></label><br>
        <input id="pdf_disk" name="pdf_disk" type="text" size="60" placeholder="local">
    </div>
    <br>
    <button type="submit" id="submit-btn">Generate PDF</button>
</form>

<div id="status-box" style="margin-top:1rem; display:none;">
    <p id="status-msg">Queuing job...</p>
    <a id="download-link" href="#" style="display:none; font-weight:bold;">Download PDF</a>
</div>

{{-- -- All odd pages (ZIP) -- --}}
<hr style="margin:2rem 0;">
<h2>All odd pages - ZIP of booklet-based PDFs</h2>
<p><small>Odd pages are added booklet-by-booklet, and a new PDF starts every 1000 pages. This can run for several hours on large assessments.</small></p>
<form id="zip-form">
    @csrf
    <div>
        <label for="zip_assessment_ids">Assessment IDs <small>(comma-separated)</small></label><br>
        <textarea id="zip_assessment_ids" name="assessment_ids" rows="3" cols="60"
            placeholder="euclid-z-french-student-form, ...">euclid-z-french-student-form</textarea>
    </div>
    <div>
        <label for="max_page">Highest page number (odd pages up to this)</label><br>
        <input id="max_page" name="max_page" type="number" min="1" value="39">
    </div>
    <div>
        <label for="zip_json_path">Booklet/Page JSON path <small>(optional, relative to storage/app)</small></label><br>
        <input id="zip_json_path" name="json_path" type="text" size="60" placeholder="crowdmark-cache/custom/booklet-pages.json">
    </div>
    <div>
        <label for="zip_json_disk">JSON disk <small>(optional, default: local)</small></label><br>
        <input id="zip_json_disk" name="json_disk" type="text" size="60" placeholder="local">
    </div>
    <div>
        <label for="zip_save_path">ZIP save path <small>(optional, relative to storage/app)</small></label><br>
        <input id="zip_save_path" name="zip_save_path" type="text" size="60" placeholder="crowdmark-pdfs/custom/odd-pages.zip">
    </div>
    <div>
        <label for="zip_disk">ZIP disk <small>(optional, default: local)</small></label><br>
        <input id="zip_disk" name="zip_disk" type="text" size="60" placeholder="local">
    </div>
    <br>
    <button type="submit" id="zip-submit-btn">Generate ZIP</button>
</form>

<div id="zip-status-box" style="margin-top:1rem; display:none;">
    <p id="zip-status-msg">Queuing job...</p>
    <a id="zip-download-link" href="#" style="display:none; font-weight:bold;">Download ZIP</a>
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
                linkEl.style.display = 'inline';
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
                    linkEl.style.display = 'inline';
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

        jsonDownloadLink.style.display = 'none';
        jsonStatusMsg.textContent = 'Queuing JSON cache job...';
        jsonStatusBox.style.display = 'block';
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

        dlLink.style.display = 'none';
        statusMsg.textContent = 'Queuing job...';
        statusBox.style.display = 'block';
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

        zipDlLink.style.display = 'none';
        zipStatusMsg.textContent = 'Queuing job...';
        zipStatusBox.style.display = 'block';
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
