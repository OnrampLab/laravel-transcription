<?php

namespace OnrampLab\Transcription\Tests\Unit;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\Jobs\ConfirmTranscriptionJob;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Tests\Classes\TranscriptionProviders\CallbackableProvider;
use OnrampLab\Transcription\Tests\Classes\TranscriptionProviders\ConfirmableProvider;
use OnrampLab\Transcription\Tests\TestCase;
use OnrampLab\Transcription\TranscriptionManager;
use OnrampLab\Transcription\ValueObjects\Transcription;

class TranscriptionManagerTest extends TestCase
{
    private MockInterface $confirmableProviderMock;

    private MockInterface $callbackableProviderMock;

    private TranscriptionManager $manager;

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('transcription.providers.confirmable_provider', ['driver' => 'confirmable_driver']);
        $app['config']->set('transcription.providers.callbackable_provider', ['driver' => 'callbackable_driver']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->confirmableProviderMock = Mockery::mock(ConfirmableProvider::class);
        $this->callbackableProviderMock = Mockery::mock(CallbackableProvider::class);

        $this->manager = new TranscriptionManager($this->app);
        $this->manager->addProvider('confirmable_driver', fn (array $config) => $this->confirmableProviderMock);
        $this->manager->addProvider('callbackable_driver', fn (array $config) => $this->callbackableProviderMock);
    }

    /**
     * @test
     * @testWith ["confirmable_provider", true]
     *           ["callbackable_provider", false]
     */
    public function make_should_work(string $providerName, bool $isConfirmationJobDispatched): void
    {
        $this->app['config']->set('transcription.default', $providerName);

        $audioUrl = 'https://www.example.com/audio/test.wav';
        $languageCode = 'en-US';
        $transcription = new Transcription([
            'id' => Str::uuid(),
            'status' => TranscriptionStatusEnum::PROCESSING,
        ]);

        $this->{Str::camel($providerName) . "Mock"}
            ->shouldReceive('transcribe')
            ->once()
            ->with($audioUrl, $languageCode)
            ->andReturn($transcription);

        $this->manager->make($audioUrl, $languageCode);

        $transcript = Transcript::latest()->first();

        $this->assertEquals($transcript->type, Str::kebab(Str::camel($providerName)));
        $this->assertEquals($transcript->external_id, $transcription->id);
        $this->assertEquals($transcript->status, $transcription->status->value);

        if ($isConfirmationJobDispatched) {
            Queue::assertPushed(ConfirmTranscriptionJob::class);
        } else {
            Queue::assertNotPushed(ConfirmTranscriptionJob::class);
        }
    }
}
