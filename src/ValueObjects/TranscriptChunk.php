<?php

namespace OnrampLab\Transcription\ValueObjects;

use Exception;
use Illuminate\Support\Collection;
use JsonSerializable;
use OnrampLab\Transcription\Models\TranscriptSegment;

class TranscriptChunk implements JsonSerializable
{
    /**
     * The text content of the transcript chunk.
     */
    public string $content;

    /**
     * The segment sections within the transcript chunk.
     *
     * @var Collection<TranscriptChunkSection>
     */
    public Collection $sections;

    public function __construct(array $data)
    {
        $this->content = $data['content'];
        $this->sections = $data['sections'];
    }

    public function findLocatedSegment(int $offset): TranscriptSegment
    {
        $section = $this->sections->first(fn (TranscriptChunkSection $section) => $offset >= $section->startOffset && $offset < $section->endOffset);

        if (!$section) {
            throw new Exception('Provided offset is not existed in any sections.');
        }

        return $section->segment;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'sections' => $this->sections->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
