<?php

namespace OnrampLab\Transcription\Contracts;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\ValueObjects\EntityAudio;

interface AudioRedactor
{
    /**
     * Redact the audio file of transcript for PII entities.
     *
     * @param Collection<EntityAudio> $entityAudios
     */
    public function redact(Transcript $transcript, Collection $entityAudios, Filesystem $audioDisk, string $audioFolder): void;
}
