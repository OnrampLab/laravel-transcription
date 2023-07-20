<?php

namespace OnrampLab\Transcription\Listeners;

use OnrampLab\Transcription\Events\TranscriptCompletedEvent;
use OnrampLab\Transcription\Jobs\RedactTranscriptJob;

class RedactTranscriptListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(TranscriptCompletedEvent $event)
    {
        $transcript = $event->transcript;

        if (!$transcript->is_redacted) {
            return;
        }

        RedactTranscriptJob::dispatch($transcript);
    }
}
