<?php

namespace OnrampLab\Transcription\Enums;

enum TranscriptionStatusEnum: string
{
    case PROCESSING = 'processing';
    case FAILED = 'failed';
    case COMPLETED = 'completed';
}
