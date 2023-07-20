<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Transcription
    |--------------------------------------------------------------------------
    |
    | | Default Provider Name
    |
    | Here you may define a default provider.
    |
    |--------------------------------------------------------------------------
    |
    | | Available Providers
    |
    | Here you may configure the provider information for each service that
    | is used by your application. You are free to add additional providers
    | as required.
    |
    | Supported drivers: "aws_transcribe"
    |
    */
    'transcription' => [
        'default' => env('TRANSCRIPTION_PROVIDER', 'aws_transcribe'),
        'providers' => [
            'aws_transcribe' => [
                'driver' => 'aws_transcribe',
                'access_key' => env('AWS_ACCESS_KEY_ID'),
                'access_secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                'tags' => [],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    |
    | | Default Detector Name
    |
    | Here you may define a default PII entity detector.
    |
    |--------------------------------------------------------------------------
    |
    | | Available Detectors
    |
    | Here you may configure the PII entity detectors information for each service
    | that is used by your application. You are free to add additional detectors
    | as required.
    |
    | Supported drivers: "aws_comprehend"
    |
    |--------------------------------------------------------------------------
    |
    | | Job Max Tries & Queue
    |
    | These options configure the behavior of redaction job so you can control
    | how many times the job should be attempted and the queue that a job should
    | be pushed onto.
    |
    */
    'redaction' => [
        'default' => env('TRANSCRIPTION_DETECTOR', 'aws_comprehend'),
        'detectors' => [
            'aws_comprehend' => [
                'driver' => 'aws_comprehend',
                'access_key' => env('AWS_ACCESS_KEY_ID'),
                'access_secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            ],
        ],
        'tries' => 3,
        'queue' => 'default',
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
