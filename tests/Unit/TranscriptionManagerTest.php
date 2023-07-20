<?php

namespace OnrampLab\Transcription\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use OnrampLab\Transcription\Contracts\Callbackable;
use OnrampLab\Transcription\Contracts\Confirmable;
use OnrampLab\Transcription\Enums\PiiEntityTypeEnum;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\Events\TranscriptCompletedEvent;
use OnrampLab\Transcription\Jobs\ConfirmTranscriptionJob;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Models\TranscriptSegment;
use OnrampLab\Transcription\Tests\Classes\AudioTranscribers\CallbackableTranscriber;
use OnrampLab\Transcription\Tests\Classes\AudioTranscribers\ConfirmableTranscriber;
use OnrampLab\Transcription\Tests\Classes\PiiEntityDetectors\GeneralDetector;
use OnrampLab\Transcription\Tests\TestCase;
use OnrampLab\Transcription\TranscriptionManager;
use OnrampLab\Transcription\ValueObjects\PiiEntity;
use OnrampLab\Transcription\ValueObjects\Transcription;

class TranscriptionManagerTest extends TestCase
{
    private MockInterface $confirmableTranscriberMock;

    private MockInterface $callbackableTranscriberMock;

    private MockInterface $generalDetectorMock;

    private TranscriptionManager $manager;

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('transcription.transcription.transcribers.confirmable_transcriber', ['driver' => 'confirmable_driver']);
        $app['config']->set('transcription.transcription.transcribers.callbackable_transcriber', ['driver' => 'callbackable_driver']);
        $app['config']->set('transcription.redaction.detectors.general_detector', ['driver' => 'general_driver']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Event::fake();

        $this->confirmableTranscriberMock = Mockery::mock(ConfirmableTranscriber::class);
        $this->callbackableTranscriberMock = Mockery::mock(CallbackableTranscriber::class);
        $this->generalDetectorMock = Mockery::mock(GeneralDetector::class);

        $this->manager = new TranscriptionManager($this->app);
        $this->manager->addTranscriber('confirmable_driver', fn (array $config) => $this->confirmableTranscriberMock);
        $this->manager->addTranscriber('callbackable_driver', fn (array $config) => $this->callbackableTranscriberMock);
        $this->manager->addDetector('general_driver', fn (array $config) => $this->generalDetectorMock);
    }

    /**
     * @test
     * @testWith ["confirmable_transcriber"]
     *           ["callbackable_transcriber"]
     */
    public function make_should_work(string $transcriberName): void
    {
        $this->app['config']->set('transcription.transcription.default', $transcriberName);

        $audioUrl = 'https://www.example.com/audio/test.wav';
        $languageCode = 'en-US';
        $shouldRedact = true;
        $transcription = new Transcription([
            'id' => Str::uuid(),
            'status' => TranscriptionStatusEnum::PROCESSING,
        ]);
        /** @var MockInterface $transcriberMock */
        $transcriberMock = $this->{Str::camel($transcriberName) . "Mock"};

        if ($transcriberMock instanceof Callbackable) {
            $callbackMethod = 'POST';
            $callbackUrl = route('transcription.callback', ['type' => Str::kebab(Str::camel($transcriberName))]);

            $transcriberMock
                ->shouldReceive('setUp')
                ->once()
                ->with($callbackMethod, $callbackUrl);
        } else {
            $transcriberMock->shouldNotReceive('setUp');
        }

        $transcriberMock
            ->shouldReceive('transcribe')
            ->once()
            ->with($audioUrl, $languageCode)
            ->andReturn($transcription);

        $transcript = $this->manager->make($audioUrl, $languageCode, $shouldRedact);

        $this->assertEquals($transcript->type, Str::kebab(Str::camel($transcriberName)));
        $this->assertEquals($transcript->external_id, $transcription->id);
        $this->assertEquals($transcript->status, $transcription->status->value);
        $this->assertEquals($transcript->is_redacted, $shouldRedact);

        if ($transcriberMock instanceof Confirmable) {
            Queue::assertPushed(ConfirmTranscriptionJob::class);
        } else {
            Queue::assertNotPushed(ConfirmTranscriptionJob::class);
        }
    }

    /**
     * @test
     */
    public function confirm_should_work(): void
    {
        $transcript = Transcript::factory()->create([
            'type' => Str::kebab(Str::camel('confirmable_transcriber')),
            'external_id' => Str::uuid()->toString(),
            'status' => TranscriptionStatusEnum::PROCESSING,
        ]);
        $transcription = new Transcription([
            'id' => $transcript->external_id,
            'status' => TranscriptionStatusEnum::COMPLETED,
        ]);

        $this->confirmableTranscriberMock
            ->shouldReceive('fetch')
            ->once()
            ->with($transcript->external_id)
            ->andReturn($transcription);

        $this->confirmableTranscriberMock
            ->shouldReceive('parse')
            ->once()
            ->withArgs(function (...$args) use ($transcription, $transcript) {
                return $args[0]->id === $transcription->id
                    && $args[1]->id === $transcript->id;
            });

        $this->manager->confirm($transcript->type, $transcript->external_id);

        $transcript->refresh();

        $this->assertEquals($transcript->status, $transcription->status->value);

        Event::assertDispatched(TranscriptCompletedEvent::class, fn (TranscriptCompletedEvent $event) => $event->transcript->id === $transcript->id);
    }

    /**
     * @test
     */
    public function callback_should_work(): void
    {
        $transcript = Transcript::factory()->create([
            'type' => Str::kebab(Str::camel('callbackable_transcriber')),
            'external_id' => Str::uuid()->toString(),
            'status' => TranscriptionStatusEnum::PROCESSING,
        ]);
        $transcription = new Transcription([
            'id' => $transcript->external_id,
            'status' => TranscriptionStatusEnum::COMPLETED,
        ]);

        $requestHeader = [
            'host' => ['www.example.com'],
            'connection' => ['keep-alive'],
            'user-agent' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36'],
        ];
        $requestBody = [
            'id' => $transcript->external_id,
            'status' => 'completed',
            'transcript' => [
                'text' => 'Hello Word.',
            ],
        ];

        $this->callbackableTranscriberMock
            ->shouldReceive('validate')
            ->once()
            ->with($requestHeader, $requestBody)
            ->andReturn($transcription);

        $this->callbackableTranscriberMock
            ->shouldReceive('process')
            ->once()
            ->with($requestHeader, $requestBody)
            ->andReturn($transcription);

        $this->callbackableTranscriberMock
            ->shouldReceive('parse')
            ->once()
            ->withArgs(function (...$args) use ($transcription, $transcript) {
                return $args[0]->id === $transcription->id
                    && $args[1]->id === $transcript->id;
            });

        $this->manager->callback($transcript->type, $requestHeader, $requestBody);

        $transcript->refresh();

        $this->assertEquals($transcript->status, $transcription->status->value);

        Event::assertDispatched(TranscriptCompletedEvent::class, fn (TranscriptCompletedEvent $event) => $event->transcript->id === $transcript->id);
    }

    /**
     * @test
     */
    public function redact_should_work(): void
    {
        $this->app['config']->set('transcription.redaction.default', 'general_detector');

        $transcript = Transcript::factory()->create(['is_redacted' => true]);

        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'content' => 'Hi, this is Eric from Fake Service.',
        ]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'content' => 'How are you doing?',
        ]);
        TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'content' => 'Yeah, but you guys call the wrong number. This number is 123-456-7890',
        ]);

        $contents = $transcript->segments->pluck('content')->join("\n");
        $languageCode = $transcript->language_code;

        $this->generalDetectorMock
            ->shouldReceive('detect')
            ->once()
            ->with($contents, $languageCode)
            ->andReturn([
                new PiiEntity([
                    'type' => PiiEntityTypeEnum::NAME,
                    'value' => 'Eric',
                    'offset' => 12,
                ]),
                new PiiEntity([
                    'type' => PiiEntityTypeEnum::PHONE_NUMBER,
                    'value' => '123-456-7890',
                    'offset' => 112,
                ]),
            ]);

        $this->manager->redact($transcript);

        $transcript->unsetRelations();

        $this->assertEquals($transcript->segments[0]->content_redacted, 'Hi, this is **** from Fake Service.');
        $this->assertEquals($transcript->segments[1]->content_redacted, 'How are you doing?');
        $this->assertEquals($transcript->segments[2]->content_redacted, 'Yeah, but you guys call the wrong number. This number is ************');
    }
}
