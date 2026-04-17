<?php

namespace Waterloobae\CrowdmarkDashboard;
// include_once '../src/API.php';
// include_once '../src/Assessment.php';
use Waterloobae\CrowdmarkDashboard\API;
use Waterloobae\CrowdmarkDashboard\Assessment;

class Course{
    protected object $logger;
    protected string $course_id = '';
    protected string $course_name = '';
    protected string $created_at = '';
    protected string $end_point = '';
    protected object $response;
    protected array $assessments = [];
    protected array $assessment_ids = [];
    protected bool $assessment_ids_loaded = false;

    public function __construct($course, object $logger)
    {
        $this->logger = $logger;

        if (is_object($course) && isset($course->id)) {
            $this->course_id = (string) $course->id;
            $this->end_point = 'api/courses/' . $this->course_id;
            $this->course_name = (string) ($course->attributes->name ?? '');

            $createdAt = 'created-at';
            $this->created_at = (string) ($course->attributes->$createdAt ?? '');
            return;
        }

        $this->course_id = (string) $course;
        $this->end_point = 'api/courses/' . $this->course_id;

        $api = new API( $this->logger );
        $api->exec($this->end_point);
        $this->response = $api->getResponse();
        $this->course_name = (string) $this->response->data->attributes->name;

        // "-" does not work in PHP Standard Ojbect variable names
        $temp = "created-at";
        $this->created_at = (string) $this->response->data->attributes->$temp;

    }

    public function setAssessment($course_id)
    {
        $api = new API( $this->logger );
        $api->exec('api/courses/' . $course_id . '/assessments');
        $response = $api->getResponse();
        foreach ($response->data as $assessment) {
            $this->assessments[] = new Assessment($assessment->id, $this->logger);
        }
     }

    public function setAssessmentIds($course_id)
    {
        $api = new API( $this->logger );
        $api->exec('api/courses/' . $course_id . '/assessments');
        $response = $api->getResponse();
        $this->assessment_ids = [];
        foreach ($response->data as $assessment) {
            $this->assessment_ids[] = $assessment->id;
        }
        $this->assessment_ids_loaded = true;
    }

    public function getAssessments()
    {
       return $this->assessments;
    }
    
    public function getAssessmentIds()
    {
         if (!$this->assessment_ids_loaded) {
              $this->setAssessmentIds($this->course_id);
         }

       return $this->assessment_ids;
    }

    public function getCourseName()
    {
        return $this->course_name;
    }
}
