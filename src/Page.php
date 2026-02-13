<?php

namespace Waterloobae\CrowdmarkDashboard;

class Page{
    protected object $logger;
    protected string $assessment_id;
    protected string $booklet_id;
    protected array $response_ids = [];
    protected string $page_id;
    protected string $page_url;
    protected string $page_number;
 
    public function __construct(string $assessment_id,string $booklet_id, object $page, object $logger)
    {
        $this->logger = $logger;
        $this->assessment_id = $assessment_id;
        $this->booklet_id = $booklet_id;
        foreach($page->relationships->responses->data as $response){
            $this->response_ids[] = $response->id;
        }
        $this->page_id = $page->id;
        $this->page_url = $page->attributes->url;
        $this->page_number = $page->attributes->number;
    }

    public function getResponseIds()
    {
        return $this->response_ids;
    }

    public function getPageId()
    {
        return $this->page_id;
    }

    public function getPageUrl()
    {
        return $this->page_url;
    }

    public function getPageNumber()
    {
        return $this->page_number;
    }

    public function getAssessmentId()
    {
        return $this->assessment_id;
    }

    public function getBookletId()
    {
        return $this->booklet_id;
    }
}
