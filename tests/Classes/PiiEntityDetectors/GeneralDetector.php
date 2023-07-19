<?php

namespace OnrampLab\Transcription\Tests\Classes\PiiEntityDetectors;

use OnrampLab\Transcription\Contracts\PiiEntityDetector;

class GeneralDetector implements PiiEntityDetector
{
    /**
     * Detect the input text for entities that contain personally identifiable information (PII).
     *
     * @return array<PiiEntity>
     */
    public function detect(string $text, string $languageCode): array
    {
        return [];
    }
}
