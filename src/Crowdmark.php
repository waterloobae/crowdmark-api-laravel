<?php
namespace Waterloobae\CrowdmarkDashboard;

include_once 'Logger.php';

use Waterloobae\CrowdmarkDashboard\API;
use Waterloobae\CrowdmarkDashboard\Course;
use Waterloobae\CrowdmarkDashboard\Assessment;
use Waterloobae\CrowdmarkDashboard\Logger;

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
     */
    public function generateOddPagesPdfZip(array $assessment_ids, int $maxPage = 39): string
    {
        // ── 1. Load all booklets and their pages (once) ──────────────────────
        $assessments = [];
        foreach ($assessment_ids as $assessment_id) {
            $temp = new Assessment($assessment_id, $this->logger);
            $temp->setAssessmentPages($temp->getBooklets());
            $assessments[] = $temp;
        }

        // ── 2. Index page URLs by page number ─────────────────────────────────
        // [ page_number => [ url, url, … ] ]
        $urlsByPage = [];
        foreach ($assessments as $assessment) {
            foreach ($assessment->getBooklets() as $booklet) {
                foreach ($booklet->getPages() as $page) {
                    $n = (int) $page->getPageNumber();
                    $urlsByPage[$n][] = $page->getPageUrl();
                }
            }
        }

        // ── 3. Build one PDF per odd page number ─────────────────────────────
        $zipPath = tempnam(sys_get_temp_dir(), 'cm_odd_') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP archive at ' . $zipPath);
        }

        $pagesAdded = 0;
        $pdfTmpFiles = [];
        for ($pageNum = 1; $pageNum <= $maxPage; $pageNum += 2) {
            $urls = $urlsByPage[$pageNum] ?? [];
            if (empty($urls)) {
                $this->logger->setWarning("No URLs found for page {$pageNum} — skipping.");
                continue;
            }

            $pdf      = new Fpdi();
            $tmpFiles = [];

            foreach ($urls as $url) {
                $imageBytes = $this->fetchImageBytes($url);
                if ($imageBytes === '') {
                    continue;
                }

                $imgPath = tempnam(sys_get_temp_dir(), 'cm_img_') . '.jpg';
                file_put_contents($imgPath, $imageBytes);
                $tmpFiles[] = $imgPath;

                $size = @getimagesize($imgPath);
                if ($size === false) {
                    continue;
                }

                [$w, $h] = $size;
                $pdf->AddPage('P', [$w, $h]);
                $pdf->Image($imgPath, 0, 0, $w, $h);
                $pagesAdded++;
            }

            foreach ($tmpFiles as $f) {
                if (file_exists($f)) {
                    unlink($f);
                }
            }

            // Write PDF to temp file instead of holding in memory
            $tmpPdfPath = tempnam(sys_get_temp_dir(), 'cm_pdf_') . '.pdf';
            $pdf->Output('F', $tmpPdfPath);
            
            // Add file to ZIP (file is read when zip->close() is called)
            $zip->addFile($tmpPdfPath, "Page_{$pageNum}.pdf");
            $pdfTmpFiles[] = $tmpPdfPath; // Store for deletion after zip closes
        }

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

        return $zipPath;
    }

    public function downloadPagesByPageNumber(array $assessment_ids, string $page_number)
    {
        $assessments = [];
        foreach($assessment_ids as $assessment_id) {
            $temp = new Assessment($assessment_id, $this->logger);
            $temp->setAssessmentPages($temp->getBooklets());
            $assessments[] = $temp;

        }   

        $pageUrls = [];
        foreach($assessments as $assessment) {
            foreach($assessment->getBooklets() as $booklet) {
                foreach($booklet->getPages() as $page) {
                    if($page->getPageNumber() == $page_number){
                        $pageUrls[] = $page->getPageUrl();
                    }
                }
            }
        }

        if (empty($pageUrls)) {
            throw new \RuntimeException(
                'No page URLs found for page number ' . $page_number . ' across ' .
                array_sum(array_map(fn($a) => count($a->getBooklets()), $assessments)) .
                ' booklets. The page number may not exist, or page data could not be loaded.'
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

