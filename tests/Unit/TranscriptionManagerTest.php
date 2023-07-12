<?php

namespace OnrampLab\Transcription\Tests\Unit;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use OnrampLab\Transcription\Contracts\Callbackable;
use OnrampLab\Transcription\Contracts\Confirmable;
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
     * @testWith ["confirmable_provider"]
     *           ["callbackable_provider"]
     */
    public function make_should_work(string $providerName): void
    {
        $this->app['config']->set('transcription.default', $providerName);

        $audioUrl = 'https://www.example.com/audio/test.wav';
        $languageCode = 'en-US';
        $transcription = new Transcription([
            'id' => Str::uuid(),
            'status' => TranscriptionStatusEnum::PROCESSING,
        ]);
        /** @var MockInterface $providerMock */
        $providerMock = $this->{Str::camel($providerName) . "Mock"};

        if ($providerMock instanceof Callbackable) {
            $callbackMethod = 'POST';
            $callbackUrl = route('transcription.callback', ['type' => Str::kebab(Str::camel($providerName))]);

            $providerMock
                ->shouldReceive('setUp')
                ->once()
                ->with($callbackMethod, $callbackUrl);
        } else {
            $providerMock->shouldNotReceive('setUp');
        }

        $providerMock
            ->shouldReceive('transcribe')
            ->once()
            ->with($audioUrl, $languageCode)
            ->andReturn($transcription);

        $transcript = $this->manager->make($audioUrl, $languageCode);

        $this->assertEquals($transcript->type, Str::kebab(Str::camel($providerName)));
        $this->assertEquals($transcript->external_id, $transcription->id);
        $this->assertEquals($transcript->status, $transcription->status->value);

        if ($providerMock instanceof Confirmable) {
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
            'type' => Str::kebab(Str::camel('confirmable_provider')),
            'external_id' => Str::uuid()->toString(),
            'status' => TranscriptionStatusEnum::PROCESSING,
        ]);
        $transcription = new Transcription([
            'id' => $transcript->external_id,
            'status' => TranscriptionStatusEnum::COMPLETED,
        ]);

        $this->confirmableProviderMock
            ->shouldReceive('fetch')
            ->once()
            ->with($transcript->external_id)
            ->andReturn($transcription);

        $this->confirmableProviderMock
            ->shouldReceive('parse')
            ->once()
            ->withArgs(function (...$args) use ($transcription, $transcript) {
                return $args[0]->id === $transcription->id
                    && $args[1]->id === $transcript->id;
            });

        $this->manager->confirm($transcript->type, $transcript->external_id);

        $transcript->refresh();

        $this->assertEquals($transcript->status, $transcription->status->value);
    }

    /**
     * @test
     */
    public function callback_should_work(): void
    {
        $transcript = Transcript::factory()->create([
            'type' => Str::kebab(Str::camel('callbackable_provider')),
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

        $this->callbackableProviderMock
            ->shouldReceive('validate')
            ->once()
            ->with($requestHeader, $requestBody)
            ->andReturn($transcription);

        $this->callbackableProviderMock
            ->shouldReceive('process')
            ->once()
            ->with($requestHeader, $requestBody)
            ->andReturn($transcription);

        $this->callbackableProviderMock
            ->shouldReceive('parse')
            ->once()
            ->withArgs(function (...$args) use ($transcription, $transcript) {
                return $args[0]->id === $transcription->id
                    && $args[1]->id === $transcript->id;
            });

        $this->manager->callback($transcript->type, $requestHeader, $requestBody);

        $transcript->refresh();

        $this->assertEquals($transcript->status, $transcription->status->value);
    }
}
