<?php

namespace OnrampLab\Transcription\ValueObjects;

use JsonSerializable;
use OnrampLab\Transcription\Enums\PiiEntityTypeEnum;

class PiiEntity implements JsonSerializable
{
    /**
     * The type of specific entity.
     */
    public PiiEntityTypeEnum $type;

    /**
     * The value of specific entity.
     */
    public string $value;

    /**
     * The zero-based offset for the entity value.
     */
    public int $offset;

    public function __construct(array $data)
    {
        $this->type = $data['type'];
        $this->value = $data['value'];
        $this->offset = $data['offset'];
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'value' => $this->value,
            'offset' => $this->offset,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
