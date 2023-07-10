<?php

namespace OnrampLab\Transcription\Contracts;

use OnrampLab\Transcription\ValueObjects\Transcription;

interface Confirmable
{
    /**
     * Fetch transcription data from third-party service.
     */
    public function fetch(string $id): Transcription;
}
