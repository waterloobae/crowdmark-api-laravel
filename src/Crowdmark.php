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
    protected static $thisPath = "";

    public function __construct()
    {
        // constructor
        $this->logger = new Logger();
        $this->setThisPath();
        $api = new API( $this->logger );
        $api->exec('api/courses');
        $this->api_response = $api->getResponse();
        $course_data = array();
        foreach ($this->api_response->data as $course_data) {
            $this->courses[] = new Course($course_data->id, $this->logger);
            $this->course_ids[] = $course_data->id;
        }
        $this->setAssessmentIDs();
    }

    public function setThisPath(){
        $this_site_root = $_SERVER['DOCUMENT_ROOT'];
        
        if (strpos(__DIR__, $this_site_root) !== false) {
            $absolutePath = str_replace($this_site_root, '', __DIR__);
        } else {
            $dir = __DIR__;
            $parts = explode(DIRECTORY_SEPARATOR, trim($dir, DIRECTORY_SEPARATOR));
            array_shift($parts); // Remove the first (top-most) directory
            $absolutePath = DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
        }
        self::$thisPath = $absolutePath;
        return;
    }

    public function setAssessmentIDs()
    {
        foreach ($this->courses as $course) {
            $this->assessment_ids = array_merge($this->assessment_ids, $course->getAssessmentIds());
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

    public function createDownloadLinks(string $type, array $course_names, ?string $page_number = null): string
    {
        $valid_encoded_course_names = [];
        $webRootPath = self::$thisPath;

        $output = [];
        switch($type) {
            case "page":
                $output[] = "<h2>3. Download Booklet Pages</h2>";
                break;
            case "studentinfo":
                $output[] = "<h2>3. Download Student Information</h2>";
                break;
            case "studentemaillist":
                $output[] = "<h2>3. Download Student Email List</h2>";
                break;
            case "grader":
                $output[] = "<h2>3. Download Grader's Grading List</h2>";
                break;
            case "grading":
                $output[] = "<h2>3. Download Grading Status</h2>";
                break;
            case "uploadedmatched":
                $output[] = "<h2>3. Download Uploaded and Matched Counts</h2>";
                break;
            case "integritycheck":
                $output[] = "<h2>3. Download Integrity Check Report</h2>";
                break;
        }
        
        foreach($course_names as $course_name) {
            $is_valid = false;
            
            foreach($this->courses as $course) {
                if($course->getCourseName() == $course_name) {
                    $is_valid = true;
                    break;
                }
            }
            $encoded_course_name = urlencode($course_name);
            
            $download_link = $webRootPath."/Download.php?type=" . $type . "&course_name=" . $encoded_course_name. "&page_number=" . $page_number;
            if($is_valid) {
                $valid_encoded_course_names[] = $encoded_course_name;    
                $output[] = '<a href="' . $download_link . '" download onclick="this.innerText=\'Loading '.$course_name.'. Please wait!\'; this.style.pointerEvents = \'none\';">Download (' . $course_name . ')</a><br>';
            } else {
                $output[] = "Invalid course name: " . $course_name . "<br>";
            }

        }

        $output[] = "<br>";
        if(empty($valid_encoded_course_names)) {
            $output[] = "No valid course names found.<br>";
        }else{
            $download_link = $webRootPath."/Download.php?type=" . $type . "&course_name=" . implode("~", $valid_encoded_course_names). "&page_number=" . $page_number;
            $output[] = '<a href="' . $download_link . '" download onclick="this.innerText=\'Loading All Courses. Please wait!\'; this.style.pointerEvents = \'none\';">Download All Course</a><br><br>';
        }

        return implode('', $output);
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
        if (class_exists(Http::class)) {
            $response = Http::timeout(60)->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            return '';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $image = curl_exec($ch);
        curl_close($ch);

        return is_string($image) ? $image : '';
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

    public function getAPIResponse()
    {
        return $this->api_response;
    }

}

