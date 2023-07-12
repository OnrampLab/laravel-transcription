<?php

namespace OnrampLab\Transcription\Contracts;

use OnrampLab\Transcription\ValueObjects\Transcription;

interface Callbackable
{
    /**
     * Validate callback request from third-party service.
     */
    public function validate(array $requestHeader, array $requestBody): void;

    /**
     * Process callback request from third-party service.
     */
    public function process(array $requestHeader, array $requestBody): Transcription;

    /**
     * Set up callback request's HTTP method & URL.
     */
    public function setUp(string $httpMethod, string $url): void;
}
