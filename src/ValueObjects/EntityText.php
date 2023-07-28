<?php

namespace OnrampLab\Transcription\ValueObjects;

use JsonSerializable;

class EntityText implements JsonSerializable
{
    /**
     * The PII entity within the text content.
     */
    public PiiEntity $entity;

    /**
     * The zero-based index of the transcript segment that the entity is within.
     */
    public int $segmentIndex;

    /**
     * The zero-based offset from the beginning of the transcript segment to the first character in the entity.
     */
    public int $startOffset;

    /**
     * The zero-based offset from the beginning of the transcript segment to the last character in the entity.
     */
    public int $endOffset;

    public function __construct(array $data)
    {
        $this->entity = $data['entity'];
        $this->segmentIndex = $data['segment_index'];
        $this->startOffset = $data['start_offset'];
        $this->endOffset = $data['end_offset'];
    }

    public function toArray(): array
    {
        return [
            'entity' => $this->entity->toArray(),
            'segment_index' => $this->segmentIndex,
            'start_offset' => $this->startOffset,
            'end_offset' => $this->endOffset,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
