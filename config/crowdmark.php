<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Crowdmark Base URL
    |--------------------------------------------------------------------------
    |
    | The root URL of the Crowdmark API. Override via CROWDMARK_BASE_URL if
    | you are pointing at a staging or self-hosted instance.
    |
    */

    'base_url' => env('CROWDMARK_BASE_URL', 'https://app.crowdmark.com/'),

    /*
    |--------------------------------------------------------------------------
    | Crowdmark API Key
    |--------------------------------------------------------------------------
    |
    | Your Crowdmark API key. Store it in .env as CROWDMARK_API_KEY and never
    | commit the value to source control.
    |
    */

    'api_key' => env('CROWDMARK_API_KEY'),

];
