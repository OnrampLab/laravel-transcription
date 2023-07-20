<?php

namespace OnrampLab\Transcription\Tests\Classes\AudioTranscribers;

use OnrampLab\Transcription\Contracts\AudioTranscriber;
use OnrampLab\Transcription\Contracts\Callbackable;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\ValueObjects\Transcription;

class CallbackableTranscriber implements AudioTranscriber, Callbackable
{
    /**
     * Transcribe audio file into text records in specific language.
     */
    public function transcribe(string $audioUrl, string $languageCode): Transcription
    {
        return new Transcription([]);
    }

    /**
     * Parse transcripts result of transcription and persist them into database.
     */
    public function parse(Transcription $transcription, Transcript $transcript): void
    {
        return;
    }

    /**
     * Validate callback request from third-party service.
     */
    public function validate(array $requestHeader, array $requestBody): void
    {
        return;
    }

    /**
     * Process callback request from third-party service.
     */
    public function process(array $requestHeader, array $requestBody): Transcription
    {
        return new Transcription([]);
    }

    /**
     * Set up callback request's HTTP method & URL.
     */
    public function setUp(string $httpMethod, string $url): void
    {
        return;
    }
}
