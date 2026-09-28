<?php

use Waterloobae\CrowdmarkApiLaravel\Tests\TestCase;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

pest()->extend(TestCase::class)->in('Feature');

function withCrowdmarkApiSettings(\Closure $callback): mixed
{
    $names = ['CROWDMARK_BASE_URL', 'CROWDMARK_API_KEY'];
    $original = [];

    foreach ($names as $name) {
        $original[$name] = [
            'process' => getenv($name),
            'env_exists' => array_key_exists($name, $_ENV),
            'env' => $_ENV[$name] ?? null,
            'server_exists' => array_key_exists($name, $_SERVER),
            'server' => $_SERVER[$name] ?? null,
        ];

        putenv($name . '=');
        $_ENV[$name] = '';
        $_SERVER[$name] = '';
    }

    config([
        'services.crowdmark.base_url' => 'https://crowdmark.test/',
        'services.crowdmark.api_key' => 'test-api-key',
    ]);

    try {
        return $callback();
    } finally {
        foreach ($original as $name => $values) {
            if ($values['process'] === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $values['process']);
            }

            if ($values['env_exists']) {
                $_ENV[$name] = $values['env'];
            } else {
                unset($_ENV[$name]);
            }

            if ($values['server_exists']) {
                $_SERVER[$name] = $values['server'];
            } else {
                unset($_SERVER[$name]);
            }
        }
    }
}