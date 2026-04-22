<?php
namespace Waterloobae\CrowdmarkApiLaravel;

include_once 'Logger.php';

use Waterloobae\CrowdmarkApiLaravel\API;
use Waterloobae\CrowdmarkApiLaravel\Course;
use Waterloobae\CrowdmarkApiLaravel\Assessment;
use Waterloobae\CrowdmarkApiLaravel\Logger;

use Illuminate\Support\Facades\Http;
use setasign\Fpdi\Fpdi;

class Crowdmark
{
    protected object $logger;
    protected array $courses = [];
    protected array $course_ids = [];
    protected array $assessment_ids = [];

    protected object $api_response;

    public function __construct()
    {
        // constructor
        $this->logger = new Logger();
        $api = new API( $this->logger );
        $api->exec('api/courses');
        $this->api_response = $api->getResponse();
        $course_data = array();
        foreach ($this->api_response->data as $course_data) {
            $this->courses[] = new Course($course_data, $this->logger);
            $this->course_ids[] = $course_data->id;
        }
    }

    public function setAssessmentIDs()
    {
        if ($this->assessment_ids !== []) {
            return;
        }

        $this->assessment_ids = [];

        if ($this->course_ids === []) {
            return;
        }

        $api = new API($this->logger);

        try {
            $api->exec('api/assessments');
        } catch (\Throwable $e) {
            $this->logger->setWarning('Assessment ID fetch failed on first page.');
            return;
        }

        $firstResponse = $api->getResponse();

        $this->appendAssessmentIdsFromResponse($firstResponse);

        $total = (int) ($firstResponse->meta->{'total'} ?? 0);
        $pageSize = (int) ($firstResponse->meta->{'page-size'} ?? 100);
        if ($pageSize <= 0) {
            $pageSize = 100;
        }

        $totalPages = max(1, (int) ceil($total / $pageSize));
        if ($totalPages > 1) {
            $endPoints = [];
            for ($page = 2; $page <= $totalPages; $page++) {
                $endPoints[] = 'api/assessments?page[number]=' . $page;
            }

            $api->multiExec($endPoints);
            $responses = $api->getResponses();

            foreach ($responses as $response) {
                if (is_object($response)) {
                    $this->appendAssessmentIdsFromResponse($response);
                }
            }

            foreach ($endPoints as $index => $endPoint) {
                if (array_key_exists($index, $responses)) {
                    continue;
                }

                try {
                    $api->exec($endPoint);
                    $this->appendAssessmentIdsFromResponse($api->getResponse());
                } catch (\Throwable $e) {
                    $this->logger->setWarning('Assessment ID fetch failed on fallback page ' . ($index + 2) . '.');
                }
            }
        }

        $this->assessment_ids = array_values(array_unique($this->assessment_ids));
    }   

    private function appendAssessmentIdsFromResponse(object $response): void
    {
        foreach ($response->data ?? [] as $assessment) {
            if (isset($assessment->id)) {
                $this->assessment_ids[] = (string) $assessment->id;
            }
        }
    }

    public function returnAssessmentIDs(array $course_names)
    {
        $assessment_ids = [];
        foreach($this->courses as $course) {
            if(in_array($course->getCourseName(), $course_names)) {
                $assessment_ids = array_merge($assessment_ids, $course->getAssessmentIds());
            }
        }
        return $assessment_ids;
    }

    public function getBookletPageCacheKey(array $assessment_ids): string
    {
        $normalized = $this->normalizeAssessmentIds($assessment_ids);
        return sha1(implode('|', $normalized));
    }

    public function saveBookletPageIndexJson(array $assessment_ids, bool $forceRefresh = false, ?string $savePath = null): array
    {
        $index = $this->getOrBuildBookletPageIndex($assessment_ids, $forceRefresh, $savePath);
        $cacheKey = (string) ($index['cache_key'] ?? $this->getBookletPageCacheKey($assessment_ids));
        $cachePath = $this->resolveBookletPageSavePath($savePath, $cacheKey);

        $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode booklet/page JSON cache.');
        }

        if (file_put_contents($cachePath, $json) === false) {
            throw new \RuntimeException('Failed to write booklet/page JSON cache to disk.');
        }

        return [
            'cache_key' => $cacheKey,
            'path' => $cachePath,
            'count' => count($index['booklet_pages'] ?? []),
            'created_at' => (string) ($index['created_at'] ?? ''),
            'updated_at' => (string) ($index['updated_at'] ?? ''),
        ];
    }

    public function loadBookletPageIndexJsonByAssessmentIds(array $assessment_ids, ?string $savePath = null): ?array
    {
        $cacheKey = $this->getBookletPageCacheKey($assessment_ids);
        $cachePath = $this->resolveBookletPageSavePath($savePath, $cacheKey);
        if (!is_file($cachePath)) {
            return null;
        }

        $raw = file_get_contents($cachePath);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function getOrBuildBookletPageIndex(array $assessment_ids, bool $forceRefresh = false, ?string $savePath = null): array
    {
        $cacheKey = $this->getBookletPageCacheKey($assessment_ids);
        $cached = $this->loadBookletPageIndexJsonByAssessmentIds($assessment_ids, $savePath);

        if (!$forceRefresh) {
            if (is_array($cached) && isset($cached['booklet_pages']) && is_array($cached['booklet_pages'])) {
                if (!isset($cached['created_at'])) {
                    $cached['created_at'] = $this->deriveIndexCreatedAt($cached['booklet_pages']);
                }
                if (!isset($cached['updated_at'])) {
                    $cached['updated_at'] = $this->deriveIndexUpdatedAt($cached['booklet_pages'], (string) ($cached['created_at'] ?? ''));
                }

                return $cached;
            }
        }

        $index = $this->buildBookletPageIndex($assessment_ids);
        $index['cache_key'] = $cacheKey;
        if (is_array($cached) && isset($cached['created_at'])) {
            $index['created_at'] = (string) $cached['created_at'];
        }
        $index['updated_at'] = $this->deriveIndexUpdatedAt(
            $index['booklet_pages'] ?? [],
            (string) ($cached['updated_at'] ?? $index['created_at'] ?? '')
        );

        $cachePath = $this->resolveBookletPageSavePath($savePath, $cacheKey);
        $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            file_put_contents($cachePath, $json);
        }

        return $index;
    }

    private function buildBookletPageIndex(array $assessment_ids): array
    {
        $normalizedAssessmentIds = $this->normalizeAssessmentIds($assessment_ids);

        $bookletPages = [];
        foreach ($normalizedAssessmentIds as $assessment_id) {
            $assessment = new Assessment($assessment_id, $this->logger);
            $assessment->setAssessmentPages($assessment->getBooklets());

            foreach ($assessment->getBooklets() as $booklet) {
                foreach ($booklet->getPages() as $page) {
                    $pageNumber = (int) $page->getPageNumber();
                    if ($pageNumber <= 0 || ($pageNumber % 2) === 0) {
                        continue;
                    }

                    $selfLink = trim((string) $page->getSelfLink());
                    if ($selfLink === '') {
                        $selfLink = 'api/pages/' . $page->getPageId();
                    }

                    $bookletPages[] = [
                        'assessment_id' => (string) $assessment->getAssessmentID(),
                        'booklet_id' => (string) $booklet->getBookletId(),
                        'booklet_number' => (string) $booklet->getBookletNumber(),
                        'page_number' => (string) $pageNumber,
                        'self_link' => $selfLink,
                        'page_url' => (string) $page->getPageUrl(),
                        'created_at' => (string) ($page->getCreatedAt() ?: ''),
                        'updated_at' => (string) ($page->getUpdatedAt() ?: $page->getCreatedAt() ?: ''),
                    ];
                }
            }
        }

        $indexCreatedAt = $this->deriveIndexCreatedAt($bookletPages);
        $indexUpdatedAt = $this->deriveIndexUpdatedAt($bookletPages, $indexCreatedAt);

        return [
            'version' => 1,
            'created_at' => $indexCreatedAt,
            'updated_at' => $indexUpdatedAt,
            'assessment_ids' => $normalizedAssessmentIds,
            'booklet_pages' => $bookletPages,
        ];
    }

    private function normalizeAssessmentIds(array $assessment_ids): array
    {
        $normalized = array_values(array_filter(array_map(static function ($id) {
            return trim((string) $id);
        }, $assessment_ids)));

        sort($normalized);

        return array_values(array_unique($normalized));
    }

    private function getBookletPageCacheDirectory(): string
    {
        $base = function_exists('storage_path')
            ? storage_path('app/crowdmark-cache')
            : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crowdmark-cache';

        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }

        return $base;
    }

    private function getBookletPageCacheFilePath(string $cacheKey): string
    {
        return $this->getBookletPageCacheDirectory() . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    }

    private function resolveBookletPageSavePath(?string $savePath, string $cacheKey): string
    {
        if ($savePath === null || trim($savePath) === '') {
            return $this->getBookletPageCacheFilePath($cacheKey);
        }

        $normalized = str_replace('\\', '/', trim($savePath));
        $normalized = ltrim($normalized, '/');
        if ($normalized === '') {
            return $this->getBookletPageCacheFilePath($cacheKey);
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \InvalidArgumentException('Invalid JSON save path.');
            }
        }

        if (pathinfo($normalized, PATHINFO_EXTENSION) === '') {
            $normalized .= '.json';
        }

        $base = function_exists('storage_path')
            ? storage_path('app')
            : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);

        $targetPath = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $targetDirectory = dirname($targetPath);

        if (!is_dir($targetDirectory)) {
            @mkdir($targetDirectory, 0755, true);
        }

        return $targetPath;
    }

    //=================================
    //
    // Functions with Business Logic
    //=================================
    public function generateIntegrityCheckReport(array $assessment_ids)
    {
        $assessments = [];
        foreach($assessment_ids as $assessment_id) {
            $temp = new Assessment($assessment_id, $this->logger);
            $temp->setResponses($temp->getBooklets());
            $assessments[] = $temp;
        }   

        $filename = 'Integrity_Check_Report-' . date('Ymd-His') . '.csv';
        $rows = [];
        $rows[] = ['Course Name', 'Assessment Name', 'Booklet Number', 'Booklet ID', 'Responses Count', 'Enrollment ID'];

        foreach($assessments as $assessment) {
            $response_count = count($assessment->getQuestions());
            foreach($assessment->getBooklets() as $booklet) {
                if( ($booklet->getResponsesCount() > 0 && $booklet->getEnrollmentId() == "NA") ||
                    ($booklet->getResponsesCount() != $response_count && $booklet->getEnrollmentId() != "NA")) {                    
                    $rows[] = [
                        $assessment->getCourseName(),
                        $assessment->getAssessmentName(),
                        $booklet->getBookletNumber(),
                        $booklet->getBookletId(),
                        $booklet->getResponsesCount(),
                        $booklet->getEnrollmentId()
                    ];
                }
            }
        }

        return $this->downloadCsv($filename, $rows);

    }

    public function generateUploadedMatchedCounts(array $assessment_ids)
    {
        $assessments = [];
        foreach($assessment_ids as $assessment_id) {
            $temp = new Assessment($assessment_id, $this->logger);
            $temp->setUploadedAndMatchedCounts();
            $assessments[] = $temp;
        }   

        $totalUploaded = 0;
        $totalMatched = 0;

        $filename = 'Uploaded_Matched-' . date('Ymd-His') . '.csv';
        $rows = [];
        $rows[] = ['Assessment ID', 'Uploaded', 'Matched'];

        foreach ($assessments as $assessment) {
            $totalUploaded += $assessment->getUploadedCount();
            $totalMatched += $assessment->getMatchedCount();        
            $rows[] = [
                $assessment->getAssessmentName(),
                $assessment->getUploadedCount(),
                $assessment->getMatchedCount()
            ];
        }

        $rows[] = ['Total', $totalUploaded, $totalMatched];

        return $this->downloadCsv($filename, $rows);
    }

    public function generateGradingStatus(array $assessment_ids)
    {
        $graded_counts = [];
        foreach($assessment_ids as $assessment_id) {
            $temp = new Assessment($assessment_id, $this->logger);
            $graded_counts['course'] = $temp->getCourseName();
            $courseName = $graded_counts['course'];
            $temp->setUploadedAndMatchedCounts();
            $temp->setGradedCountsFromBooklets();
        
            $graded_counts[$courseName]['!booket_count'] = $temp->getUploadedCount();
            // Add counts per course, excluding '!course'
            foreach ($temp->getGradedCounts() as $key => $value) {
                if ($key !== 'course') {
                    if (!isset($graded_counts[$courseName][$key])) {
                        $graded_counts[$courseName][$key] = 0;
                    }
                    $graded_counts[$courseName][$key] += $value;
                }
            }
        }
        
        foreach ($graded_counts as &$subarray) {
            if (is_array($subarray)) {
                ksort($subarray);
            }
        }
        
        $filename = "graded_counts_" . date("Ymd-His") . ".csv";
        $rows = [];
        
        // Get headers from the first subarray
        $headers = ['Course'];
        

        foreach($graded_counts as $firstSubarray) {
            if(is_array($firstSubarray)){
                break;
            }
        }

        if(!empty($firstSubarray)) {
            $headers = array_merge($headers, array_keys($firstSubarray));
        }

        $rows[] = $headers;

        foreach ($graded_counts as $course => $counts) {
            if(is_array($counts)){
                $rows[] = array_merge([$course], $counts);
            }
        }

        return $this->downloadCsv($filename, $rows);
    }

    public function generateGradersGradingList(array $assessment_ids)
    {
        $assessments = [];
        foreach($assessment_ids as $assessment_id) {
            $temp = new Assessment($assessment_id, $this->logger);
            $temp->setResponses($temp->getBooklets());
            $assessments[] = $temp;
        }   
        
        $question_id_to_names = [];
        $grader_id_to_names = [];
        $grader_id_to_emails = [];
        $grades = [];
        
        foreach($assessments as $assessment) {
            // 1. Creating questions array
            foreach($assessment->getQuestions() as $question) {
                $question_id_to_names[$question->getQuestionId()] = $question->getQuestionName();
            }
        
            // 2. Creating Graders array
            foreach($assessment->getGraders() as $grader) {
                $grader_id_to_names[$grader->getUserId()] = $grader->getName();
                $grader_id_to_emails[$grader->getUserId()] = $grader->getEmail();
                //echo($grader->getEmail() . "<br>");
            }
        }
        
        // Sort questions by question name
        uasort($question_id_to_names, function($a, $b) {
            return strcmp($a, $b);
        });
        
        foreach($assessments as $assessment) {
            // 3. Creating Grade Counts array
            foreach($assessment->getBooklets() as $booklet) {
                foreach($booklet->getResponses() as $response) {
                    if (!isset($grades[$response->getGraderId()][$response->getQuestionLabel()])) {
                        $grades[$response->getGraderId()][$response->getQuestionLabel()] = 0;
                    }
                    $grades[$response->getGraderId()][$response->getQuestionLabel()]++;
                }
            }
        }
        
        // Sort graders by name and then by question label
        uksort($grades, function($a, $b) use ($grader_id_to_names) {
            return strcmp($grader_id_to_names[$a] ?? 'Unknown', $grader_id_to_names[$b] ?? 'Unknown');
        });
        
        $filename = 'graders_' . date('Ymd-His') . '.csv';

        $rows = [];
        $header = ['Grader Name', 'Grader Email'];
        $question_labels = [];
        foreach ($grades as $questions) {
            $question_labels = array_merge($question_labels, array_keys($questions));
        }
        $question_labels = array_values(array_unique($question_labels));
        sort($question_labels);

        $header = array_merge($header, array_map('strtoupper', $question_labels));
        $rows[] = $header;

        foreach ($grades as $grader_id => $questions) {
            $row = [
                $grader_id_to_names[$grader_id] ?? 'Unknown',
                $grader_id_to_emails[$grader_id] ?? 'Unknown'
            ];
            foreach ($question_labels as $label) {
                $row[] = $questions[$label] ?? 0;
            }
            $rows[] = $row;
        }

        return $this->downloadCsv($filename, $rows);
    }


    public function generateStudentEmailList(array $assessment_ids)
    {
        $email_list = [];

        foreach($assessment_ids as $assessment_id) {
            $temp = new Assessment($assessment_id, $this->logger);
            $temp->setMatchedEmailList();
            $email_list = array_merge($email_list, $temp->getMatchedEmailList());
        }   
        $datetime = date('Ymd-His');
        return $this->downloadText(
            'student_email_list_' . $datetime . '.txt',
            implode("\n", $email_list)
        );
    }

    public function generateStudentInformation(array $assessment_ids)
    {
        $student_list = [];
        $student_list[] = "Email, First Name, Last Name, Participant ID";

        foreach($assessment_ids as $assessment_id) {
            $temp = new Assessment($assessment_id, $this->logger);
            $temp->setStdentCSVList();
            $student_list = array_merge($student_list, $temp->getStudentCSVList());
        }   
        $datetime = date('Ymd-His');
        return $this->downloadText(
            'student_list_' . $datetime . '.csv',
            implode("\n", $student_list),
            'text/csv'
        );
    }

    /**
     * Generate one PDF per odd page number (1, 3, 5, … $maxPage) across all given
     * assessments, then bundle them into a ZIP archive.
     *
     * Returns the path to a temporary ZIP file (caller must delete it).
     *
     * @param  string[] $assessment_ids
     * @param  int      $maxPage  Highest page number to consider (inclusive). Default 39.
     * @param  string|null $jsonPath Optional path (relative to storage/app) for booklet/page JSON cache.
     */
    public function generateOddPagesPdfZip(array $assessment_ids, int $maxPage = 39, ?string $jsonPath = null): string
    {
        $cacheKey = $this->getBookletPageCacheKey($assessment_ids);
        $baselineIndex = $this->loadBookletPageIndexJsonByAssessmentIds($assessment_ids, $jsonPath);
        $index = $this->buildBookletPageIndex($assessment_ids);
        $index['cache_key'] = $cacheKey;
        if (is_array($baselineIndex) && isset($baselineIndex['created_at'])) {
            $index['created_at'] = (string) $baselineIndex['created_at'];
        }
        $index['updated_at'] = $this->deriveIndexUpdatedAt(
            $index['booklet_pages'] ?? [],
            (string) ($baselineIndex['updated_at'] ?? $index['created_at'] ?? '')
        );

        $baselineByPageKey = [];
        foreach (($baselineIndex['booklet_pages'] ?? []) as $entry) {
            $pageKey = $this->bookletPageBaselineKey($entry);
            if ($pageKey === '') {
                continue;
            }

            $baselineByPageKey[$pageKey] = $this->normalizedUpdatedAt($entry);
        }

        // Build booklet-based odd-page index.
        $booklets = [];
        foreach (($index['booklet_pages'] ?? []) as $entry) {
            $pageNumber = (int) ($entry['page_number'] ?? 0);
            $pageUrl = trim((string) ($entry['page_url'] ?? ''));

            if ($pageNumber <= 0 || $pageNumber > $maxPage || ($pageNumber % 2) === 0 || $pageUrl === '') {
                continue;
            }

            $pageKey = $this->bookletPageBaselineKey($entry);
            if ($pageKey === '') {
                continue;
            }

            $latestUpdatedAt = $this->normalizedUpdatedAt($entry);
            $baselineUpdatedAt = $baselineByPageKey[$pageKey] ?? null;

            if ($baselineUpdatedAt !== null && $baselineUpdatedAt === $latestUpdatedAt) {
                continue;
            }

            $bookletId = (string) ($entry['booklet_id'] ?? 'unknown');
            $bookletNumber = (string) ($entry['booklet_number'] ?? '');

            if (!isset($booklets[$bookletId])) {
                $booklets[$bookletId] = [
                    'booklet_id' => $bookletId,
                    'booklet_number' => $bookletNumber,
                    'pages' => [],
                ];
            }

            $booklets[$bookletId]['pages'][] = [
                'page_number' => $pageNumber,
                'page_url' => $pageUrl,
            ];
        }

        if (empty($booklets)) {
            throw new \RuntimeException('No new or updated odd pages were found compared to baseline JSON.');
        }

        $booklets = array_values($booklets);
        usort($booklets, static function (array $a, array $b): int {
            $aNum = trim((string) ($a['booklet_number'] ?? ''));
            $bNum = trim((string) ($b['booklet_number'] ?? ''));

            if (is_numeric($aNum) && is_numeric($bNum)) {
                return (int) $aNum <=> (int) $bNum;
            }

            return strnatcmp($aNum, $bNum);
        });

        foreach ($booklets as &$booklet) {
            usort($booklet['pages'], static function (array $a, array $b): int {
                return ((int) $a['page_number']) <=> ((int) $b['page_number']);
            });
        }
        unset($booklet);

        // Build PDFs booklet-by-booklet, splitting aggressively to cap memory growth.
        $maxPagesPerPdf = 120;
        $memoryLimit = $this->parseBytesFromIni((string) ini_get('memory_limit'));
        $memoryFlushThreshold = $memoryLimit > 0
            ? (int) floor($memoryLimit * 0.65)
            : 0;
        $zipPath = tempnam(sys_get_temp_dir(), 'cm_odd_') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP archive at ' . $zipPath);
        }

        $pagesAdded = 0;
        $partNumber = 1;
        $currentPdfPageCount = 0;
        $pdf = new Fpdi();
        $pdfTmpFiles = [];

        $flushCurrentPdf = function () use (&$pdf, &$currentPdfPageCount, &$partNumber, &$pdfTmpFiles, $zip): void {
            if ($currentPdfPageCount === 0) {
                return;
            }

            $tmpPdfPath = tempnam(sys_get_temp_dir(), 'cm_pdf_') . '.pdf';
            $pdf->Output('F', $tmpPdfPath);
            if (method_exists($pdf, 'Close')) {
                $pdf->Close();
            }

            $zip->addFile($tmpPdfPath, sprintf('Odd_Booklet_Part_%03d.pdf', $partNumber));
            $pdfTmpFiles[] = $tmpPdfPath;

            $partNumber++;
            $currentPdfPageCount = 0;
            $pdf = new Fpdi();

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        };

        foreach ($booklets as $booklet) {
            foreach ($booklet['pages'] as $page) {
                $url = (string) $page['page_url'];

                $imgPath = $this->fetchImageToTempFile($url);
                if ($imgPath === null) {
                    continue;
                }

                try {
                    $size = @getimagesize($imgPath);
                    if ($size === false) {
                        continue;
                    }

                    [$w, $h] = $size;
                    $pdf->AddPage('P', [$w, $h]);
                    $pdf->Image($imgPath, 0, 0, $w, $h);
                    $pagesAdded++;
                    $currentPdfPageCount++;

                    $shouldFlushForMemory = $memoryFlushThreshold > 0 && memory_get_usage(true) >= $memoryFlushThreshold;
                    if ($currentPdfPageCount >= $maxPagesPerPdf || $shouldFlushForMemory) {
                        $flushCurrentPdf();
                    }
                } finally {
                    if (file_exists($imgPath)) {
                        unlink($imgPath);
                    }
                }
            }
        }

        $flushCurrentPdf();

        $zip->close();

        // Clean up temp PDF files now that ZIP is closed
        foreach ($pdfTmpFiles as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }

        if ($pagesAdded === 0) {
            unlink($zipPath);
            throw new \RuntimeException('No page images could be downloaded — the ZIP would be empty.');
        }

        $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode refreshed booklet/page JSON cache.');
        }

        $cachePath = $this->resolveBookletPageSavePath($jsonPath, $cacheKey);
        if (file_put_contents($cachePath, $json) === false) {
            throw new \RuntimeException('Failed to write refreshed booklet/page JSON cache to disk.');
        }

        return $zipPath;
    }

    private function bookletPageBaselineKey(array $entry): string
    {
        $assessmentId = trim((string) ($entry['assessment_id'] ?? ''));
        $bookletId = trim((string) ($entry['booklet_id'] ?? ''));
        $pageNumber = trim((string) ($entry['page_number'] ?? ''));

        if ($assessmentId === '' || $bookletId === '' || $pageNumber === '') {
            return '';
        }

        return $assessmentId . '|' . $bookletId . '|' . $pageNumber;
    }

    private function normalizedUpdatedAt(array $entry): string
    {
        $updatedAt = trim((string) ($entry['updated_at'] ?? ''));
        if ($updatedAt !== '') {
            return $updatedAt;
        }

        return trim((string) ($entry['created_at'] ?? ''));
    }

    private function deriveIndexCreatedAt(array $bookletPages, string $fallback = ''): string
    {
        $oldestTimestamp = null;
        $oldestRaw = '';

        foreach ($bookletPages as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $candidate = trim((string) ($entry['created_at'] ?? ''));
            if ($candidate === '') {
                $candidate = trim((string) ($entry['updated_at'] ?? ''));
            }
            if ($candidate === '') {
                continue;
            }

            $candidateTimestamp = strtotime($candidate);
            if ($candidateTimestamp === false) {
                continue;
            }

            if ($oldestTimestamp === null || $candidateTimestamp < $oldestTimestamp) {
                $oldestTimestamp = $candidateTimestamp;
                $oldestRaw = $candidate;
            }
        }

        if ($oldestRaw !== '') {
            return $oldestRaw;
        }

        return $fallback;
    }

    private function deriveIndexUpdatedAt(array $bookletPages, string $fallback = ''): string
    {
        $latestTimestamp = null;
        $latestRaw = '';

        foreach ($bookletPages as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $candidate = $this->normalizedUpdatedAt($entry);
            if ($candidate === '') {
                continue;
            }

            $candidateTimestamp = strtotime($candidate);
            if ($candidateTimestamp === false) {
                continue;
            }

            if ($latestTimestamp === null || $candidateTimestamp > $latestTimestamp) {
                $latestTimestamp = $candidateTimestamp;
                $latestRaw = $candidate;
            }
        }

        if ($latestRaw !== '') {
            return $latestRaw;
        }

        return $fallback;
    }

    private function parseBytesFromIni(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '-1') {
            return -1;
        }

        $suffix = strtolower(substr($trimmed, -1));
        $number = (int) $trimmed;

        switch ($suffix) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
                break;
        }

        return $number;
    }

    private function fetchImageToTempFile(string $url): ?string
    {
        if (!class_exists(Http::class)) {
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'cm_img_');
        if ($tmpPath === false) {
            return null;
        }

        $tmpPathWithExt = $tmpPath . '.jpg';
        @rename($tmpPath, $tmpPathWithExt);

        try {
            $response = Http::timeout(60)
                ->withOptions(['sink' => $tmpPathWithExt])
                ->get($url);

            if (!$response->successful() || !is_file($tmpPathWithExt) || filesize($tmpPathWithExt) === 0) {
                @unlink($tmpPathWithExt);
                return null;
            }

            return $tmpPathWithExt;
        } catch (\Throwable $e) {
            @unlink($tmpPathWithExt);
            return null;
        }
    }

    public function downloadPagesByPageNumber(array $assessment_ids, string $page_number)
    {
        $index = $this->getOrBuildBookletPageIndex($assessment_ids);
        $pageUrls = [];
        foreach (($index['booklet_pages'] ?? []) as $entry) {
            if ((string) ($entry['page_number'] ?? '') === (string) $page_number) {
                $url = (string) ($entry['page_url'] ?? '');
                if ($url !== '') {
                    $pageUrls[] = $url;
                }
            }
        }

        if (empty($pageUrls)) {
            throw new \RuntimeException(
                'No page URLs found for page number ' . $page_number . ' across ' .
                count($index['booklet_pages'] ?? []) .
                ' cached page rows. The page number may not exist, or cached page data could not be loaded.'
            );
        }

        $pdf = new Fpdi();
        $tempFiles = [];
        foreach ($pageUrls as $url) {
            $image = $this->fetchImageBytes($url);
            if ($image === '') {
                continue;
            }

            $imagePath = tempnam(sys_get_temp_dir(), 'img') . '.jpg';
            file_put_contents($imagePath, $image);
            $tempFiles[] = $imagePath;

            $imageSize = @getimagesize($imagePath);
            if ($imageSize === false) {
                continue;
            }

            [$width, $height] = $imageSize;
            $pdf->AddPage('P', [$width, $height]);
            $pdf->Image($imagePath, 0, 0, $width, $height);
        }

        foreach ($tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $dateTime = date("Ymd_His");
        $fileName = "Page_".$page_number."_". $dateTime . ".pdf";
        $pdfContent = $pdf->Output('S');

        return $this->downloadBinary(
            $fileName,
            $pdfContent,
            'application/pdf'
        );

        // $pdf->Output('F', sys_get_temp_dir() . "/cover_pages_" . $dateTime . ".pdf");
        // echo '<a href="'. sys_get_temp_dir() . $dateTime . '.pdf" download>Download PDF</a>';
    }

    private function fetchImageBytes(string $url): string
    {
        if (!class_exists(Http::class)) {
            return '';
        }

        try {
            $response = Http::timeout(60)->get($url);
            return $response->successful() ? $response->body() : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function downloadCsv(string $fileName, array $rows)
    {
        if (function_exists('response')) {
            return response()->streamDownload(function () use ($rows) {
                $output = fopen('php://output', 'w');
                foreach ($rows as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
            }, $fileName, [
                'Content-Type' => 'text/csv',
            ]);
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        $output = fopen('php://output', 'w');
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    private function downloadText(string $fileName, string $content, string $contentType = 'text/plain')
    {
        if (function_exists('response')) {
            return response($content, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);
        }

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo $content;
        exit;
    }

    private function downloadBinary(string $fileName, string $content, string $contentType)
    {
        if (function_exists('response')) {
            return response($content, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);
        }

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo $content;
        exit;
    }
    

    //=================================
    // Ordinary Setters and Getters
    //=================================

    public function getCourses()
    {
        return $this->courses;
    }

    public function getCourseIds()
    {
        return $this->course_ids;
    }

    public function getAssessmentIds()
    {
        if ($this->assessment_ids === []) {
            $this->setAssessmentIDs();
        }

        return $this->assessment_ids;
    }

    public function getAPIResponse()
    {
        return $this->api_response;
    }

}

