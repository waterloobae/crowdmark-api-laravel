<?php

namespace Waterloobae\CrowdmarkDashboard;
use Waterloobae\CrowdmarkDashboard\API;
use Waterloobae\CrowdmarkDashboard\Booklet;
use Waterloobae\CrowdmarkDashboard\Question;
use Waterloobae\CrowdmarkDashboard\Response;
use Waterloobae\CrowdmarkDashboard\Grader;

use Exception;

class Assessment{
    protected object $logger;
    protected string $course_id;
    protected string $course_name;

    protected string $assessment_id;
    protected string $assessment_name;
    protected string $created_at;
    protected int $booklet_count;
    protected string $end_point;

    protected array $questions = [];
    protected array $booklets = [];
    protected array $graders = [];
    protected array $matched_email_list = [];
    protected array $student_csv_list = []; 

    // Total counts
    protected int $uploaded_count = 0;
    protected int $matched_count = 0;
    protected array $graded_counts = [];

    protected object $response;

    public function __construct(string $assessment_id, object $logger)
    {
        $this->assessment_id = $assessment_id;
        $this->logger = $logger;

        $this->end_point = 'api/assessments/' . $assessment_id;
        $api = new API( $this->logger );
        $api->exec($this->end_point);
        $this->response = $api->getResponse();

        $this->assessment_name = $this->response->data->attributes->title;
        $this->booklet_count = $this->response->data->relationships->booklets->meta->count;

        // "-" does not work in PHP Standard Ojbect variable names
        $temp = "created-at";
        $this->created_at = $this->response->data->attributes->$temp;

        foreach($this->response->included as $data){
            if($data->type == "course"){
                $this->course_id = $data->id;
                $this->course_name = $data->attributes->name;
            }
        }

        $this->setQuestions($assessment_id);
        $this->setBooklets($assessment_id);
        //$this->setResponses($this->booklets); //This sets Graders and Pages
        //$this->setUploadedAndMatchedCounts();
        //$this->setGradedCounts();
    }



    public function setQuestions($assessment_id)
    {
         $api = new API( $this->logger );
         $api->exec('api/assessments/' . $assessment_id . '/questions');
         $response = $api->getResponse();
         foreach ($response->data as $question) {
             $this->questions[] = new Question($assessment_id, $question, $this->logger);
         }
    }

    public function setBooklets($assessment_id)
    {
        $self_link = 'api/assessments/' . $assessment_id . '/booklets';

        do {
            $api = new API( $this->logger );
            $api->exec($self_link);
            $response = $api->getResponse();
            // echo "==== Booklet Debug ====<br>";
            // echo("<pre>");
            // var_dump($response->data);
            // echo("</pre>");

            foreach ($response->data as $booklet) {
                $this->booklets[] = new Booklet($this->assessment_id, $booklet, $this->logger);
            }
            $self_link = $response->links->next ?? "end";
        } while ( $self_link != "end");
    }

    //This sets Graders and Pages
    public function setResponses($booklets)
    {
        if(empty($booklets)) {
            return;
        }
        
        $dup_grader_ids = [];
        foreach($booklets as $booklet){
            $end_points[] = 'api/booklets/' . $booklet->getBookletId() . '/responses';
        }

        if(empty($end_points)) {
            return;
        }

        $api = new API( $this->logger );
        $api->multiExec($end_points);
        $api_responses = $api->getResponses();

        $temp_responses = [];
        foreach($api_responses as $api_response){
            // echo "==== Response Debug ====<br>";
            // echo("<pre>");
            // var_dump($api_response->included);
            // echo("</pre>");

            foreach ($api_response->data as $data) {
                $temp_responses[] = new Response($this->assessment_id, $data, $api_response->included, $this->logger);
            }

            if (!empty($temp_responses)) {
                $booklet->setReponses($temp_responses);
            }

            if (isset($api_response->included)) {
                foreach ($api_response->included as $data) {
                    if ($data->type == "user" && !in_array($data->id, $dup_grader_ids)){ 
                        // echo($data->attributes->email."<br>");
                        $dup_grader_ids[] = $data->id;
                        $this->graders[] = new Grader($data, $this->logger);
                    }
                }
            }
            
        }
    }

    public function setAssessmentPages($booklets)
    {
        foreach($booklets as $booklet){
            $end_points[] = 'api/booklets/' . $booklet->getBookletId() . '/pages';
        }

        $api = new API( $this->logger );
        $api->multiExec($end_points);
        $api_responses = $api->getResponses();

        foreach($api_responses as $api_response){
            $temp_pages = [];
            $booklet_id = preg_replace('/\D/', '', $api_response->links->self);

            foreach ($api_response->data as $data) {
                if($data->type == "page"){
                //if($data->type == "page" && $data->attributes->number == 1){
                    $temp_pages[] = new Page($this->assessment_id, $booklet_id, $data, $this->logger);
                }
            }

            foreach ($booklets as $booklet) {
                if ($booklet->getBookletId() == $booklet_id) {
                    if (!empty($temp_pages)) {
                        $booklet->setPages($temp_pages);
                    }
                    break;
                }
            }
        }
    }

    public function setGradedCounts()
    {
        // This will be faster than setGradedCountsFromBooklets, but
        // 504 Gateway Time-out error will occur since there are too many booklets
        foreach($this->questions as $question){
            $end_point = 'api/questions/' . $question->getQuestionId() . '/responses';

            $api = new API( $this->logger );

            try {
                $api->exec($end_point);
                $response = $api->getResponse();

                foreach ($response->data as $data) {
                    if ($data->type == "response" && $data->attributes->status == "graded"){
                        $temp = $data->relationships->question->links->self;
                        $items = explode("/", $temp);
                        $question_label = end($items);
                        $this->graded_counts[$question_label] += 1;
                    }
                }
            }
            catch (Exception $e) {
                error_log('Caught exception: '.$e->getMessage());
                $this->setGradedCountsFromBooklets();
                // This will break the loop 
                // to stop going through the rest of the questions
                break;
            }

        }
    }

    public function setUploadedAndMatchedCounts()
    {
        foreach($this->booklets as $booklet) {

            if ($booklet->getResponsesCount() > 0) {
                $this->uploaded_count += 1;
            }

            if ($booklet->getEnrollmentId() !== "NA") {
                $this->matched_count += 1;
            }
        }
    }

    // This must be rewritten to used after running getResponses() 
    public function setGradedCountsFromBooklets()
    {
        // $sequence = [];
        // foreach($this->questions as $question){
        //     $sequence[ $question->getQuestionName() ] = $question->getQuestionSequenceNumber();
        //     $this->graded_counts[$question->getQuestionSequenceNumber()] = 0;
        // }

        if (empty($this->graders)) {
            $this->setResponses($this->booklets);
        }

        foreach($this->booklets as $booklet){
            foreach ($booklet->getResponses() as $response) {
               if ($response->getIsGradedStatus() == "graded") {
                    $temp = $response->getQuestionLabel();
                    if (!isset($this->graded_counts[$temp])) {
                        $this->graded_counts[$temp] = 0;
                    }
                    $this->graded_counts[$temp] += 1;
                }
            }
        }
        
        // Sorting by sequence number
        ksort($this->graded_counts, 1);
    }

    // This will be used for attendency check
    public function setMatchedEmailList()
    {
        $end_points = [];
        if(empty($this->booklets)) {
            $this->setBooklets($this->assessment_id);
        }

        foreach($this->booklets as $booklet) {
            if ($booklet->getEnrollmentId() !== "NA") {
                $end_points[] = 'api/enrollments/' . $booklet->getEnrollmentId();
            }
        }        

        $api = new API( $this->logger );
        $api->multiExec($end_points);
        $responses = $api->getResponses();
        
        foreach($responses as $response){
            if(isset($response->included)){
                foreach($response->included as $data){
                    if($data->type == "user" && !empty(trim($data->attributes->email))){

                        $this->matched_email_list[] = $data->attributes->email;
                    }
                }
            }
        }
        
    }


   // This will be used for attendency check
   public function setStdentCSVList()
   {
       $end_points = [];
       if(empty($this->booklets)) {
           $this->setBooklets($this->assessment_id);
       }

       foreach($this->booklets as $booklet) {
           if ($booklet->getEnrollmentId() !== "NA") {
               $end_points[] = 'api/enrollments/' . $booklet->getEnrollmentId();
           }
       }        

       $api = new API( $this->logger );
       $api->multiExec($end_points);
       $responses = $api->getResponses();
       
       foreach($responses as $response){
           if(isset($response->included)){
               foreach($response->included as $data){
                   if($data->type == "user" && !empty(trim($data->attributes->email))){
                       $email = $data->attributes->email;
                   }
               }

                $temp = "First Name";
                $first_name = $response->data->attributes->metadata->$temp;
                $temp = "Last Name";
                $last_name = $response->data->attributes->metadata->$temp;               
                $temp = "Student ID";
                $student_id = $response->data->attributes->metadata->$temp;

           }
           $this->student_csv_list[] = $email .",". $first_name . "," . $last_name . "," . $student_id;
       }
   }    

    public function getQuestions()
    {
         return $this->questions;
    }

    public function getAssessmentName()
    {
        return $this->assessment_name;
    }

    public function getAssessmentID()
    {
        return $this->assessment_id;
    }

    public function getBooklets()
    {
        return $this->booklets;
    }

    public function getGraders()
    {
        return $this->graders;
    }

    public function getUploadedCount()
    {
        return $this->uploaded_count;
    }

    public function getMatchedCount()
    {
        return $this->matched_count;
    }

    public function getGradedCounts()
    {
        return $this->graded_counts;
    }

    public function getMatchedEmailList()
    {
        return $this->matched_email_list;
    }

    public function getStudentCSVList(){
        return $this->student_csv_list;
    }

    public function getCourseID()
    {
        return $this->course_id;
    }

    public function getCourseName()
    {
        return $this->course_name;
    }

}
