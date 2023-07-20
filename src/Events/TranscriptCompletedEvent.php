<?php

namespace OnrampLab\Transcription\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use OnrampLab\Transcription\Models\Transcript;

class TranscriptCompletedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(
        public readonly Transcript $transcript,
    ) {
        //
    }
}
