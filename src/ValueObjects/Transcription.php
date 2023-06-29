<?php

namespace OnrampLab\Transcription\ValueObjects;

use JsonSerializable;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;

class Transcription implements JsonSerializable
{
    /**
     * The identity in third-party service.
     */
    public string $id;

    /**
     * The status of specific transcription.
     */
    public TranscriptionStatusEnum $status;

    /**
     * The final transcript result.
     */
    public ?array $result;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->status = $data['status'];
        $this->result = data_get($data, 'result');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'result' => $this->result,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
