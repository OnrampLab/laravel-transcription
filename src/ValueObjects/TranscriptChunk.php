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

    /**
     * Search the sections for the given offset and returns its index
     */
    public function search(int $offset): int
    {
        $index = $this->sections->search(fn (TranscriptChunkSection $section) => $offset >= $section->startOffset && $offset < $section->endOffset);

        if ($index === false) {
            throw new Exception('Provided offset is not existed.');
        }

        return $index;
    }

    /**
     * Get the section at a given index
     */
    public function get(int $index): TranscriptChunkSection
    {
        $section = $this->sections->get($index);

        if (!$section) {
            throw new Exception('Provided index is not existed.');
        }

        return $section;
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
