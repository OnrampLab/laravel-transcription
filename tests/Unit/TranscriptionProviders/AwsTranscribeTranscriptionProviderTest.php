<?php

namespace OnrampLab\Transcription\Tests\Unit\TranscriptionProviders;

use Aws\Result;
use Aws\TranscribeService\TranscribeServiceClient;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\Tests\TestCase;
use OnrampLab\Transcription\TranscriptionProviders\AwsTranscribeTranscriptionProvider as BaseTranscriptionProvider;

class AwsTranscribeTranscriptionProvider extends BaseTranscriptionProvider
{
    public function __construct(array $config, TranscribeServiceClient $client)
    {
        parent::__construct($config);
        $this->client = $client;
    }
}

class AwsTranscribeTranscriptionProviderTest extends TestCase
{
    private array $config;

    private string $languageCode;

    private MockInterface $clientMock;

    private AwsTranscribeTranscriptionProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'driver' => 'aws_transcribe',
            'access_key' => Str::upper(Str::random(20)),
            'access_secret' => Str::random(40),
            'region' => 'us-east-1',
            'tags' => [
                'Environment' => 'Development',
            ],
        ];
        $this->languageCode = 'en-US';

        $this->clientMock = Mockery::mock(TranscribeServiceClient::class);
        $this->provider = new AwsTranscribeTranscriptionProvider($this->config, $this->clientMock);
    }

    /**
     * @test
     * @testWith ["https://example.s3.amazonaws.com/recordings/test.wav"]
     *           ["s3://example/recordings/test.wav"]
     */
    public function transcribe_should_work(string $audioUrl): void
    {
        $result = new Result([
            'TranscriptionJob' => [
                'TranscriptionJobStatus' => 'QUEUED',
            ],
        ]);

        $this->clientMock
            ->shouldReceive('startTranscriptionJob')
            ->once()
            ->withArgs(function (array $args) {
                return Str::isUuid($args['TranscriptionJobName'])
                    && $args['Media']['MediaFileUri'] === 's3://example/recordings/test.wav'
                    && $args['Tags'] === [
                        [
                            'Key' => 'Environment',
                            'Value' => 'Development'
                        ],
                    ];
            })
            ->andReturn($result);

        $transcription = $this->provider->transcribe($audioUrl, $this->languageCode);

        $this->assertTrue(Str::isUuid($transcription->id));
        $this->assertEquals($transcription->status, TranscriptionStatusEnum::PROCESSING);
    }

    /**
     * @test
     */
    public function transcribe_should_fail_with_invalid_audio_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provided URL is not a valid Amazon S3 bucket URL');

        $audioUrl = 'https://www.example.com';

        $this->provider->transcribe($audioUrl, $this->languageCode);
    }
}
