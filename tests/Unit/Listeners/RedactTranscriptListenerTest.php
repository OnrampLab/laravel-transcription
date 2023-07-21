<?php

namespace OnrampLab\Transcription\Tests\Unit\Listeners;

use Illuminate\Support\Facades\Queue;
use OnrampLab\Transcription\Events\TranscriptCompletedEvent;
use OnrampLab\Transcription\Jobs\RedactTranscriptJob;
use OnrampLab\Transcription\Listeners\RedactTranscriptListener;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Tests\TestCase;

class RedactTranscriptListenerTest extends TestCase
{
    private RedactTranscriptListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->listener = $this->app->make(RedactTranscriptListener::class);
    }

    /**
     * @test
     * @testWith [true]
     *           [false]
     */
    public function handle_should_work(bool $isRedacted): void
    {
        $transcript = Transcript::factory()->make(['is_redacted' => $isRedacted]);
        $event = new TranscriptCompletedEvent($transcript);

        $this->listener->handle($event);

        if ($isRedacted) {
            Queue::assertPushed(RedactTranscriptJob::class);
        } else {
            Queue::assertNothingPushed(RedactTranscriptJob::class);
        }
    }
}
