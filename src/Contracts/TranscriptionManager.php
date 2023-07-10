<?php

namespace OnrampLab\Transcription\Contracts;

use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\ValueObjects\Transcription;

interface TranscriptionManager
{
    /**
     * Make transcription for audio file in specific language
     */
    public function make(string $audioUrl, string $languageCode, ?string $providerName = null): void;

    /**
     * Confirm asynchronous transcription process
     */
    public function confirm(string $type, string $externalId): Transcript;

    /**
     * Execute asynchronous transcription callback
     */
    public function callback(string $type, array $requestHeader, array $requestBody): Transcript;
}
