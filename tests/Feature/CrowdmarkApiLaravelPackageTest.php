<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Waterloobae\CrowdmarkApiLaravel\API;
use Waterloobae\CrowdmarkApiLaravel\Logger;

it('registers the package views', function () {
    expect(view()->exists('crowdmark-api-laravel::crowdmark'))->toBeTrue()
        ->and(view()->exists('crowdmark-api-laravel::filament.pages.crowdmark-example'))->toBeTrue();
});

it('sends API requests with query parameters and the configured API key', function () {
    withCrowdmarkApiSettings(function () {
        Http::fake([
            'crowdmark.test/*' => Http::response(['data' => ['id' => 'assessment-123']], 200),
        ]);

        $logger = new class {
            public function setInfo(string $message): void {}
        };

        $api = new API($logger);
        $api->exec('api/assessments?include=responses&per_page=2');

        expect($api->getResponse()->data->id)->toBe('assessment-123')
            ->and($api->getHttpCode())->toBe(200);

        Http::assertSent(function (Request $request): bool {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && parse_url($request->url(), PHP_URL_PATH) === '/api/assessments'
                && $query === [
                    'include' => 'responses',
                    'per_page' => '2',
                    'api_key' => 'test-api-key',
                ]
                && $request->hasHeader('Accept');
        });
    });
});

it('throws when the Crowdmark API responds with an error status', function () {
    withCrowdmarkApiSettings(function () {
        Http::fake([
            'crowdmark.test/*' => Http::response(['errors' => ['Unavailable']], 503),
        ]);

        $logger = new class {
            public function setInfo(string $message): void {}
        };
        $api = new API($logger);

        expect(fn () => $api->exec('api/assessments'))
            ->toThrow(Exception::class, 'HTTP 503');

        expect($api->getHttpCode())->toBe(503);
    });
});

it('stores and clears logger messages', function () {
    $logger = new Logger();

    $logger->setError('Request failed');
    $logger->setWarning('Retrying request');
    $logger->setInfo('Request started');

    expect($logger->getError())->toBe('Request failed')
        ->and($logger->getWarning())->toBe('Retrying request')
        ->and($logger->getInfo())->toBe('Request started');

    $logger->clearError();
    $logger->clearWarning();
    $logger->clearInfo();

    expect($logger->getError())->toBe('')
        ->and($logger->getWarning())->toBe('')
        ->and($logger->getInfo())->toBe('');
});