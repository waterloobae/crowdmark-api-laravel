<?php

namespace Waterloobae\CrowdmarkDashboard;

use Exception;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class API
{
    protected string $url = 'https://app.crowdmark.com/';
    protected string $api_key;

    protected object $logger;
    // $this->exec uses and returns
    protected object $api_response;
    // $this->multExec uses and returns
    protected array $api_responses = [];

    protected int $max_retries = 5;
    protected int $current_try = 0;

    protected int $httpCode;

    public function __construct(object $logger)
    {
        $this->logger = $logger;
        $this->buildApiKeyString();
    }

    public function buildApiKeyString()
    {
        if (function_exists('config')) {
            $configuredBaseUrl = config('services.crowdmark.base_url');
            if (is_string($configuredBaseUrl) && $configuredBaseUrl !== '') {
                $this->url = rtrim($configuredBaseUrl, '/') . '/';
            }

            $configuredApiKey = config('services.crowdmark.api_key');
            if (is_string($configuredApiKey) && $configuredApiKey !== '') {
                $this->api_key = $configuredApiKey;
                return;
            }
        }

        $envApiKey = env('CROWDMARK_API_KEY');
        if (is_string($envApiKey) && $envApiKey !== '') {
            $this->api_key = $envApiKey;
            return;
        }
    }

    public function exec(string $end_point)
    {
        $this->logger->setInfo('API call to ' . $end_point . ' started.');

        $response = Http::baseUrl($this->url)
            ->timeout(60)
            ->retry($this->max_retries, 1000, throw: false)
            ->acceptJson()
            ->get($end_point, ['api_key' => $this->api_key]);

        $this->httpCode = $response->status();
        $decoded = $response->json();

        if (
            $response->successful()
            && is_array($decoded)
            && !array_key_exists('errors', $decoded)
        ) {
            $this->api_response = json_decode($response->body());
            return;
        }

        throw new Exception(
            'Crowdmark API call failed for endpoint [' . $end_point . '] with HTTP ' . $this->httpCode . '.'
        );

    }

    public function multiExec(array $big_end_points)
    {
        $batch_size = 10;
        $this->api_responses = [];

        $batches = array_chunk($big_end_points, $batch_size);

        foreach ($batches as $i => $end_points) {
            $responses = Http::baseUrl($this->url)
                ->acceptJson()
                ->pool(function (Pool $pool) use ($end_points) {
                    return array_map(function (string $end_point) use ($pool) {
                        $this->logger->setInfo('API call to ' . $end_point . ' started.');

                        return $pool
                            ->timeout(60)
                            ->retry($this->max_retries, 1000, throw: false)
                            ->get($end_point, ['api_key' => $this->api_key]);
                    }, $end_points);
                });

            foreach ($responses as $j => $response) {
                $decoded = $response->json();

                if (
                    $response->successful()
                    && is_array($decoded)
                    && !array_key_exists('errors', $decoded)
                ) {
                    $this->api_responses[$i * $batch_size + $j] = json_decode($response->body());
                }
            }
        }
    }
    

    public function getResponse()
    {
        return $this->api_response;
    }
    public function getResponses()
    {
        return $this->api_responses;
    }
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    public function consoleLog($msg)
    {
        $output = $msg;
        if (is_array($output)) {
            $output = implode(',', $output);
        }

        if (function_exists('logger')) {
            logger()->warning('Crowdmark API: ' . $output);
            return;
        }

        echo "<script>console.log('Error message(s): " . $output . "' );</script>";
    }

}