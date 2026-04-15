<?php
namespace Waterloobae\CrowdmarkDashboard;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (strpos(__DIR__, '/workspaces') !== false) {
    require_once '/workspaces/vendor/autoload.php';
}else{
    $this_site_root = $_SERVER['DOCUMENT_ROOT'];
    require_once $this_site_root.'/vendor/autoload.php';
}

use Waterloobae\CrowdmarkDashboard\Crowdmark;

class Download{
    private object $crowdmark;
    private array $course_names = [];
    private array $assessment_ids = [];
    private string $page_number = "NA";
    private string $link_type = "NA";

    public function __construct(){
        // constructor
        $this->crowdmark = new Crowdmark();
    }

    public function setParams(){
        $this->course_names = isset($_GET['course_name']) && $_GET['course_name'] !== null ? explode("~", $_GET['course_name']) : [];
        $this->page_number = $_GET['page_number'] ?? "NA";    
        $this->link_type = $_GET['type'] ?? "NA";
        $this->assessment_ids = $this->crowdmark->returnAssessmentIDs($this->course_names);
    }

    public function getAssesmentIDs(){
        return $this->assessment_ids;
    }

    public function downloadPage() {
        return $this->crowdmark->downloadPagesByPageNumber($this->assessment_ids, $this->page_number);
    }

    public function generateStudentInfo() {
        return $this->crowdmark->generateStudentInformation($this->assessment_ids);
    }

    public function generateStudentEmailList() {
        return $this->crowdmark->generateStudentEmailList($this->assessment_ids);
    }

    public function generateGradersGradingList(){
        return $this->crowdmark->generateGradersGradingList($this->assessment_ids);
    }

    public function generateGradingStatus(){
        return $this->crowdmark->generateGradingStatus($this->assessment_ids);
    }

    public function generateUploadedMatchedCounts(){
        return $this->crowdmark->generateUploadedMatchedCounts($this->assessment_ids);
    }

    public function generateIntegrityCheckReport(){
        return $this->crowdmark->generateIntegrityCheckReport($this->assessment_ids);
    }

    public function createLink(){

        switch($this->link_type){
            case "page":
                return $this->downloadPage();
            case "studentinfo":
                return $this->generateStudentInfo();
            case "studentemaillist":
                return $this->generateStudentEmailList();
            case "grader": 
                return $this->generateGradersGradingList();
            case "grading":
                return $this->generateGradingStatus();
            case "uploadedmatched":
                return $this->generateUploadedMatchedCounts();
            case "integritycheck":
                return $this->generateIntegrityCheckReport();
        }

        return null;

    }

}

$dwonload = new Download();
$dwonload->setParams();
$response = $dwonload->createLink();

if (is_object($response) && method_exists($response, 'send')) {
    $response->send();
}

