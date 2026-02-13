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
        $this->crowdmark->downloadPagesByPageNumber($this->assessment_ids, $this->page_number);
    }

    public function generateStudentInfo() {
        $this->crowdmark->generateStudentInformation($this->assessment_ids);
    }

    public function generateStudentEmailList() {
        $this->crowdmark->generateStudentEmailList($this->assessment_ids);
    }

    public function generateGradersGradingList(){
        $this->crowdmark->generateGradersGradingList($this->assessment_ids);
    }

    public function generateGradingStatus(){
        $this->crowdmark->generateGradingStatus($this->assessment_ids);
    }

    public function generateUploadedMatchedCounts(){
        $this->crowdmark->generateUploadedMatchedCounts($this->assessment_ids);
    }

    public function generateIntegrityCheckReport(){
        $this->crowdmark->generateIntegrityCheckReport($this->assessment_ids);
    }

    public function createLink(){

        switch($this->link_type){
            case "page":
                $this->downloadPage();
                break;
            case "studentinfo":
                $this->generateStudentInfo();
                break;
            case "studentemaillist":
                $this->generateStudentEmailList();
                break;
            case "grader": 
                $this->generateGradersGradingList();
                break;
            case "grading":
                $this->generateGradingStatus();
                break;
            case "uploadedmatched":
                $this->generateUploadedMatchedCounts();
                break;
            case "integritycheck":
                $this->generateIntegrityCheckReport();
                break;
        }
        

    }

}

$dwonload = new Download();
$dwonload->setParams();
$dwonload->createLink();

