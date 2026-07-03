<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Filament Actions Workflow</h2>
            <p class="mt-1 text-sm text-gray-600">Use these in-page actions to queue jobs, check status, and download outputs. This page does not depend on any <code>crowdmark.*</code> web routes.</p>
            <div class="mt-4">
                <x-filament::actions
                    :actions="[
                        $this->queueJsonCacheAction(),
                        $this->checkJsonStatusAction(),
                        $this->queueSinglePagePdfAction(),
                        $this->downloadSinglePagePdfAction(),
                        $this->queueOddPagesZipAction(),
                        $this->downloadOddPagesZipAction(),
                    ]"
                    alignment="Start"
                    wrap
                />
            </div>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700">
                <li><strong>Queue JSON Cache</strong> and then <strong>Check JSON Status</strong>.</li>
                <li><strong>Queue Single Page PDF</strong> and then <strong>Download Single Page PDF</strong>.</li>
                <li><strong>Queue Odd Pages ZIP</strong> and then <strong>Download Odd Pages ZIP</strong>.</li>
            </ul>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Last Tokens</h2>
            <p class="mt-1 text-sm text-gray-600">Actions store your most recent token values for convenience.</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div>
                    <dt class="font-medium text-gray-700">JSON token</dt>
                    <dd class="text-gray-900">{{ $this->jsonToken ?: 'Not queued yet' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">PDF token</dt>
                    <dd class="text-gray-900">{{ $this->pdfToken ?: 'Not queued yet' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">ZIP token</dt>
                    <dd class="text-gray-900">{{ $this->zipToken ?: 'Not queued yet' }}</dd>
                </div>
            </dl>
        </div>
    </div>
</x-filament-panels::page>
