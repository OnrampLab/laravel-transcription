<?php

use OnrampLab\Transcription\Redactors\AudioRedactor;
use OnrampLab\Transcription\Redactors\TextRedactor;

return [
    /*
    |--------------------------------------------------------------------------
    | Transcription
    |--------------------------------------------------------------------------
    |
    | | Default Transcriber Name
    |
    | Here you may define a default transcriber.
    |
    |--------------------------------------------------------------------------
    |
    | | Available Transcribers
    |
    | Here you may configure the transcriber information for each service that
    | is used by your application. You are free to add additional transcribers
    | as required.
    |
    | Supported drivers: "aws_transcribe"
    |
    |--------------------------------------------------------------------------
    |
    | | Speaker Identification
    |
    | This option configure if the transcriber should identify speaker when
    | executing transcription.
    |
    */
    'transcription' => [
        'default' => env('TRANSCRIPTION_TRANSCRIBER', 'aws_transcribe'),
        'transcribers' => [
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
    | | Text/Audio Redactor Class
    |
    | This option configure the class file used to do text/audio redaction.
    | You are free to use your custom implemented class.
    |
    |--------------------------------------------------------------------------
    |
    | | Audio Disk & Folder
    |
    | This options configure the disk and folder used to store redacted audio
    | file. Should use any available disk in your `filesystems.php` file.
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
        'redactor' => [
            'text' => TextRedactor::class,
            'audio' => AudioRedactor::class,
        ],
        'audio' => [
            'disk' => env('TRANSCRIPTION_AUDIO_DISK', 'local'),
            'folder' => '',
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
