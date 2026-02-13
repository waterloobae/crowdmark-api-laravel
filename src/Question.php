<?php

namespace Waterloobae\CrowdmarkDashboard;

class Question{
    protected object $logger;
    protected string $assessment_id;    
    protected string $question_id;
    protected string $max_points;
    protected string $question_name;
    protected string $question_sequence_number;
    protected string $created_at;
    protected string $end_point;
    protected string $response_count;

    public function __construct(string $assessment_id, object $question, object $logger)
    {
        $this->logger = $logger;
        $this->assessment_id = $assessment_id;
        $this->question_id = $question->id;
        $this->end_point = 'questions/' . $question->id;
        $this->question_name = $question->attributes->label;
        $temp = "max-points"; // "-" does not work in PHP Standard Ojbect variable names
        $this->max_points = $question->attributes->$temp;
        $this->question_sequence_number = $question->attributes->sequence;
        $this->response_count = $question->relationships->responses->meta->count;        
        $temp = "created-at"; // "-" does not work in PHP Standard Ojbect variable names
        $this->created_at = $question->attributes->$temp;
    }

    public function getQuestionId()
    {
        return $this->question_id;
    }

    public function getQuestionName()
    {
        return $this->question_name;
    }

    public function getQuestionSequenceNumber()
    {
        return $this->question_sequence_number;
    }
}
