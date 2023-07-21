<?php

namespace OnrampLab\Transcription\Tests\Unit\Jobs;

use OnrampLab\Transcription\Facades\Transcription;
use OnrampLab\Transcription\Jobs\RedactTranscriptJob;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Tests\TestCase;

class RedactTranscriptJobTest extends TestCase
{
    private int $tries;

    private string $queue;

    private Transcript $transcript;

    private RedactTranscriptJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tries = 1;
        $this->queue = 'highest';
        $this->transcript = Transcript::factory()->create();

        $this->app['config']->set('transcription.redaction.tries', $this->tries);
        $this->app['config']->set('transcription.redaction.queue', $this->queue);

        $this->job = new RedactTranscriptJob($this->transcript);
    }

    /**
     * @test
     */
    public function handle_should_work(): void
    {
        Transcription::shouldReceive('redact')
            ->once()
            ->with($this->transcript);

        $this->app->call([$this->job, 'handle']);

        $this->assertEquals($this->job->tries, $this->tries);
        $this->assertEquals($this->job->queue, $this->queue);
    }
}
