<?php

namespace OnrampLab\Transcription\Contracts;

use OnrampLab\Transcription\ValueObjects\PiiEntity;

interface PiiEntityDetector
{
    /**
     * Detect the input text for entities that contain personally identifiable information (PII).
     *
     * @return array<PiiEntity>
     */
    public function detect(string $text, string $languageCode): array;
}
