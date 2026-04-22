<?php

namespace Waterloobae\CrowdmarkApiLaravel;

class Page{
    protected object $logger;
    protected string $assessment_id;
    protected string $booklet_id;
    protected array $response_ids = [];
    protected string $page_id;
    protected string $page_url;
    protected string $page_number;
    protected string $self_link;
    protected string $created_at;
    protected string $updated_at;
 
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
        $this->self_link = $page->links->self ?? '';

        $createdAt = 'created-at';
        $updatedAt = 'updated-at';
        $this->created_at = (string) ($page->attributes->$createdAt ?? '');
        $this->updated_at = (string) ($page->attributes->$updatedAt ?? '');
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

    public function getSelfLink()
    {
        return $this->self_link;
    }

    public function getAssessmentId()
    {
        return $this->assessment_id;
    }

    public function getBookletId()
    {
        return $this->booklet_id;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function getUpdatedAt()
    {
        return $this->updated_at;
    }
}
