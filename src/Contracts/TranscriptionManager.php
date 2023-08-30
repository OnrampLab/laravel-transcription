<?php

namespace OnrampLab\Transcription\Contracts;

use OnrampLab\Transcription\Models\Transcript;

interface TranscriptionManager
{
    /**
     * Make transcription for audio file in specific language
     */
    public function make(string $audioUrl, string $languageCode, ?int $maxSpeakerCount = null, ?bool $shouldRedact = false, ?string $transcriberName = null): Transcript;

    /**
     * Confirm asynchronous transcription process
     */
    public function confirm(string $type, string $externalId): Transcript;

    /**
     * Execute asynchronous transcription callback
     */
    public function callback(string $type, array $requestHeader, array $requestBody): Transcript;

    /**
     * Redact personally identifiable information (PII) content within transcript
     */
    public function redact(Transcript $transcript, ?string $detectorName = null): void;
}
