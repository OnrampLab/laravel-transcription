<?php

namespace OnrampLab\Transcription\Contracts;

use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\ValueObjects\Transcription;

interface TranscriptionProvider
{
    /**
     * Transcribe audio file into text records in specific language.
     */
    public function transcribe(string $audioUrl, string $languageCode): Transcription;
}
