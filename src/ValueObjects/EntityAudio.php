<?php

namespace OnrampLab\Transcription\ValueObjects;

use JsonSerializable;

class EntityAudio implements JsonSerializable
{
    /**
     * The PII entity within the audio file.
     */
    public PiiEntity $entity;

    /**
     * The time offset from the beginning of the audio to the first spoken word in the entity.
     */
    public string $startTime;

    /**
     * The time offset from the beginning of the audio to the last spoken word in the entity.
     */
    public string $endTime;

    public function __construct(array $data)
    {
        $this->entity = $data['entity'];
        $this->startTime = $data['start_time'];
        $this->endTime = $data['end_time'];
    }

    public function toArray(): array
    {
        return [
            'entity' => $this->entity->toArray(),
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
