<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Transcription Provider Name
    |--------------------------------------------------------------------------
    |
    | Here you may define a default provider.
    |
    */

    'default' => env('TRANSCRIPTION_PROVIDER', 'aws_transcribe'),

    /*
    |--------------------------------------------------------------------------
    | Transcription Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the provider information for each service that
    | is used by your application. You are free to add additional providers
    | as required.
    |
    | Supported drivers: "aws_transcribe"
    |
    */

    'providers' => [
        'aws_transcribe' => [
            'driver' => 'aws_transcribe',
            'access_key' => env('AWS_ACCESS_KEY_ID'),
            'access_secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'tags' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirmation Job
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of confirmation job so you can
    | control how many times the job should be attempted and how many seconds
    | a next job should wait before retrying again. And also the queue that
    | a job should be pushed onto.
    |
    */
    'confirmation' => [
        'tries' => 5,
        'interval' => 600,
        'queue' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback Route
    |--------------------------------------------------------------------------
    |
    | These options configure the setting of callback route so you can decide
    | the route prefix and the route middleware.
    |
    */
    'callback' => [
        'prefix' => 'api',
        'middleware' => ['api'],
    ],
];
