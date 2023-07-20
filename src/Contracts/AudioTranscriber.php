<?php

namespace OnrampLab\Transcription\Contracts;

use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\ValueObjects\Transcription;

interface AudioTranscriber
{
    /**
     * Transcribe audio file into text records in specific language.
     */
    public function transcribe(string $audioUrl, string $languageCode): Transcription;

    /**
     * Parse transcripts result of transcription and persist them into database.
     */
    public function parse(Transcription $transcription, Transcript $transcript): void;
}
