<?php

namespace OnrampLab\Transcription\Tests\Unit\Jobs;

use Illuminate\Support\Str;
use OnrampLab\Transcription\Facades\Transcription;
use OnrampLab\Transcription\Jobs\ConfirmTranscriptionJob;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Tests\TestCase;

class ConfirmTranscriptionJobTest extends TestCase
{
    private int $tries;

    private string $queue;

    private string $type;

    private string $externalId;

    private ConfirmTranscriptionJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tries = 1;
        $this->queue = 'highest';
        $this->type = 'fake-service';
        $this->externalId = Str::uuid()->toString();

        $this->app['config']->set('transcription.confirmation.tries', $this->tries);
        $this->app['config']->set('transcription.confirmation.queue', $this->queue);

        $this->job = new ConfirmTranscriptionJob($this->type, $this->externalId);
    }

    /**
     * @test
     */
    public function handle_should_work(): void
    {
        $transcript = Transcript::factory()->make();

        Transcription::shouldReceive('confirm')
            ->once()
            ->with($this->type, $this->externalId)
            ->andReturn($transcript);

        $this->app->call([$this->job, 'handle']);

        $this->assertEquals($this->job->tries, $this->tries);
        $this->assertEquals($this->job->queue, $this->queue);
    }
}
