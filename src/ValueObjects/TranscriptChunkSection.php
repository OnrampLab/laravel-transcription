<?php

namespace OnrampLab\Transcription\ValueObjects;

use JsonSerializable;
use OnrampLab\Transcription\Models\TranscriptSegment;

class TranscriptChunkSection implements JsonSerializable
{
    /**
     * The transcript segment within the transcript chunk.
     */
    public TranscriptSegment $segment;

    /**
     * The zero-based offset from the beginning of the transcript chunk to the first character in the transcript segment.
     */
    public int $startOffset;

    /**
     * The zero-based offset from the beginning of the transcript chunk to the last character in the transcript segment.
     */
    public int $endOffset;

    public function __construct(array $data)
    {
        $this->segment = $data['segment'];
        $this->startOffset = $data['start_offset'];
        $this->endOffset = $data['end_offset'];
    }

    public function toArray(): array
    {
        return [
            'segment' => $this->segment->toArray(),
            'start_offset' => $this->startOffset,
            'end_offset' => $this->endOffset,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
