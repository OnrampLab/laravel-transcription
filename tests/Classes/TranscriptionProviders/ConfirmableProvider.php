<?php

namespace OnrampLab\Transcription\Tests\Classes\TranscriptionProviders;

use OnrampLab\Transcription\Contracts\Confirmable;
use OnrampLab\Transcription\Contracts\TranscriptionProvider;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\ValueObjects\Transcription;

class ConfirmableProvider implements TranscriptionProvider, Confirmable
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
     * Fetch transcription data from third-party service.
     */
    public function fetch(string $id): Transcription
    {
        return new Transcription([]);
    }
}
