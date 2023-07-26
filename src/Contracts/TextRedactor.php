<?php

namespace OnrampLab\Transcription\Contracts;

use Illuminate\Support\Collection;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\ValueObjects\EntityText;

interface TextRedactor
{
    /**
     * Redact the text content of transcript for PII entities.
     *
     * @param Collection<EntityText> $entityTexts
     */
    public function redact(Transcript $transcript, Collection $entityTexts): void;
}
