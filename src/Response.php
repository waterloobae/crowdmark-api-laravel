<?php

namespace Waterloobae\CrowdmarkDashboard;
//include_once '../src/API.php';
use Waterloobae\CrowdmarkDashboard\API;
use Waterloobae\CrowdmarkDashboard\Page;

class Response{
    protected object $logger;
    protected string $assessment_id;
    protected string $booklet_id;
    protected string $response_id;

    protected string $score_id;
    protected string $is_graded_status;
    protected float $score;

    protected string $question_id;
    protected string $question_label;
    protected string $grader_id;

    protected array $pages = [];

    public function __construct(string $assessment_id, object $data, array $included, object $logger)
    {
        $this->logger = $logger;
        $this->assessment_id = $assessment_id;
        $this->response_id = $data->id;
        $this->question_id = $data->relationships->question->data->id;

        $temp = $data->relationships->question->links->self;
        $items = explode("/", $temp);
        $this->question_label = end($items);

        $this->score_id = $data->relationships->scores->data->id ?? "NA";
        $this->is_graded_status = $data->attributes->status;
        $this->pages = $data->relationships->pages->data ?? [];
        $this->booklet_id = $data->relationships->booklet->data->id;

        foreach ($included as $item) {

            // if Score?
            if ($item->type == "score" && $item->relationships->response->data->id == $this->response_id) {
                $this->score = $item->attributes->points ?? 0;
                $this->grader_id = $item->relationships->grader->data->id;
            }

            // if Page?
            if ($item->type == "page"){
                foreach($item->relationships->responses->data as $page_response) {
                    if($page_response->id == $this->response_id) {  
                        $this->pages[] = new Page($this->assessment_id, $this->booklet_id, $item, $this->logger);
                    }
                }
            }
        }
    }

    public function getScore()
    {
        return $this->score;
    }

    public function getQuestionId()
    {
        return $this->question_id;
    }

    public function getQuestionLabel()
    {
        return $this->question_label;
    }

    public function getGraderId()
    {
        return $this->grader_id;
    }

    public function getPages()
    {
        return $this->pages;
    }

    public function getResponseId()
    {
        return $this->response_id;
    }

    public function getBookletId()
    {
        return $this->booklet_id;
    }

    public function getIsGradedStatus()
    {
        return $this->is_graded_status;
    }

    public function getScoreId()
    {
        return $this->score_id;
    }

    public function getAssessmentId()
    {
        return $this->assessment_id;
    }

}