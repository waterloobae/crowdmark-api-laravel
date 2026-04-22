<?php

namespace Waterloobae\CrowdmarkApiLaravel;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class API
{
    protected int $longRunningExecutionTime = 7200;

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
        $envBaseUrl = env('CROWDMARK_BASE_URL');
        if (is_string($envBaseUrl) && $envBaseUrl !== '') {
            $this->url = rtrim($envBaseUrl, '/') . '/';
        }

        $envApiKey = env('CROWDMARK_API_KEY');
        if (is_string($envApiKey) && $envApiKey !== '') {
            $this->api_key = $envApiKey;
            return;
        }

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
    }

    public function exec(string $end_point)
    {
        $this->extendExecutionTimeLimit();

        $this->logger->setInfo('API call to ' . $end_point . ' started.');
        [$path, $query] = $this->splitEndpointAndParams($end_point);

        $response = Http::baseUrl($this->url)
            ->timeout(60)
            ->retry($this->max_retries, 1000, throw: false)
            ->acceptJson()
            ->get($path, $query);

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
        $this->extendExecutionTimeLimit();

        $batch_size = 5;
        $this->api_responses = [];

        $batches = array_chunk($big_end_points, $batch_size);

        foreach ($batches as $i => $end_points) {
            $batchStart = microtime(true);

            $responses = Http::baseUrl($this->url)
                ->acceptJson()
                ->pool(function (Pool $pool) use ($end_points) {
                    return array_map(function (string $end_point) use ($pool) {
                        $this->logger->setInfo('API call to ' . $end_point . ' started.');
                        [$path, $query] = $this->splitEndpointAndParams($end_point);

                        return $pool
                            ->timeout(60)
                            ->withOptions(['curl' => [CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1]])
                            ->retry($this->max_retries, 1000, throw: false)
                            ->get($path, $query);
                    }, $end_points);
                });

            foreach ($responses as $j => $response) {
                if (!$response instanceof Response) {
                    $this->logger->setInfo('API call failed for ' . ($end_points[$j] ?? $j) . ': ' . get_class($response));
                    continue;
                }

                $decoded = $response->json();

                if (
                    $response->successful()
                    && is_array($decoded)
                    && !array_key_exists('errors', $decoded)
                ) {
                    $this->api_responses[$i * $batch_size + $j] = json_decode($response->body());
                }
            }

            // Throttle: stay within ~5 requests/second.
            $elapsed = microtime(true) - $batchStart;
            $remaining = 1.0 - $elapsed;
            if ($remaining > 0) {
                usleep((int) ($remaining * 1_000_000));
            }
        }
    }

    protected function extendExecutionTimeLimit(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit($this->longRunningExecutionTime);
        }

        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', (string) $this->longRunningExecutionTime);
        }
    }

    protected function splitEndpointAndParams(string $endPoint): array
    {
        $parsed = parse_url($endPoint);
        if ($parsed === false) {
            return [$endPoint, ['api_key' => $this->api_key]];
        }

        $path = $parsed['path'] ?? $endPoint;
        $query = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $query['api_key'] = $this->api_key;

        return [$path, $query];
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