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

    'default' => env('TRANSCRIPTION_PROVIDER'),

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
];
