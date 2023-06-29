<?php

namespace OnrampLab\Transcription\Tests\Unit\TranscriptionProviders;

use Aws\Result;
use Aws\TranscribeService\TranscribeServiceClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Tests\TestCase;
use OnrampLab\Transcription\TranscriptionProviders\AwsTranscribeTranscriptionProvider as BaseTranscriptionProvider;
use OnrampLab\Transcription\ValueObjects\Transcription;

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

    /**
     * @test
     */
    public function fetch_should_work(): void
    {
        $id = Str::uuid()->toString();
        $transcriptUrl = 'https://s3.us-west-1.amazonaws.com/aws-transcribe-us-west-1-prod/111/222/333/asrOutput.json';
        $result = new Result([
            'TranscriptionJob' => [
                'TranscriptionJobStatus' => 'COMPLETED',
                'Transcript' => [
                    'TranscriptFileUri' => $transcriptUrl,
                ],
            ],
        ]);

        $this->clientMock
            ->shouldReceive('getTranscriptionJob')
            ->once()
            ->with(['TranscriptionJobName' => $id])
            ->andReturn($result);

        $this->mock(
            GuzzleClient::class,
            function ($mock) use ($transcriptUrl) {
                $mock
                    ->shouldReceive('request')
                    ->once()
                    ->with('GET', $transcriptUrl)
                    ->andReturn(new GuzzleResponse(200, [], '{}'));
            },
        );

        $transcription = $this->provider->fetch($id);

        $this->assertEquals($transcription->id, $id);
        $this->assertEquals($transcription->status, TranscriptionStatusEnum::COMPLETED);
    }

    /**
     * @test
     */
    public function parse_should_work(): void
    {
        $transcript = Transcript::factory()->create();
        $transcriptOutput = file_get_contents(__DIR__ . '/Data/aws_transcribe_transcript_output.json');
        $transcription = new Transcription([
            'id' => $transcript->external_id,
            'status' => TranscriptionStatusEnum::COMPLETED,
            'result' => json_decode($transcriptOutput, true),
        ]);

        $this->provider->parse($transcription, $transcript);

        $transcript->refresh();

        $this->assertEquals($transcript->segments->count(), 2);
        $this->assertEquals($transcript->segments[0]->content, 'Welcome to Amazon transcribe.');
        $this->assertEquals($transcript->segments[0]->start_time->toTimeString(), '00:00:00');
        $this->assertEquals($transcript->segments[0]->end_time->toTimeString(), '00:00:01');
        $this->assertEquals($transcript->segments[1]->content, 'You can use Amazon transcribe as a standalone transcription service or to add speech to text capabilities to any application.');
        $this->assertEquals($transcript->segments[1]->start_time->toTimeString(), '00:00:02');
        $this->assertEquals($transcript->segments[1]->end_time->toTimeString(), '00:00:09');
    }
}
