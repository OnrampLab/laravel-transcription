<?php

namespace OnrampLab\Transcription\Tests\Unit\PiiEntityDetectors;

use Aws\Comprehend\ComprehendClient;
use Aws\Result;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use OnrampLab\Transcription\Enums\PiiEntityTypeEnum;
use OnrampLab\Transcription\PiiEntityDetectors\AwsComprehendPiiEntityDetector as BasePiiEntityDetector;
use OnrampLab\Transcription\Tests\TestCase;
use OnrampLab\Transcription\ValueObjects\PiiEntity;

class AwsComprehendPiiEntityDetector extends BasePiiEntityDetector
{
    public function __construct(array $config, ComprehendClient $client)
    {
        parent::__construct($config);
        $this->client = $client;
    }
}

class AwsComprehendPiiEntityDetectorTest extends TestCase
{
    private array $config;

    private string $languageCode;

    private MockInterface $clientMock;

    private AwsComprehendPiiEntityDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'driver' => 'aws_comprehend',
            'access_key' => Str::upper(Str::random(20)),
            'access_secret' => Str::random(40),
            'region' => 'us-west-2',
        ];
        $this->languageCode = 'en-US';

        $this->clientMock = Mockery::mock(ComprehendClient::class);
        $this->detector = new AwsComprehendPiiEntityDetector($this->config, $this->clientMock);
    }

    /**
     * @test
     * @dataProvider textDataProvider
     */
    public function detect_should_work(string $text, array $detectedEntities, array $expectedEntities): void
    {
        $result = new Result(['Entities' => $detectedEntities]);

        $this->clientMock
            ->shouldReceive('detectPiiEntities')
            ->once()
            ->with([
                'LanguageCode' => 'en',
                'Text' => $text,
            ])
            ->andReturn($result);

        $actualEntities = $this->detector->detect($text, $this->languageCode);

        collect($actualEntities)
            ->each(function (PiiEntity $actualEntity, int $index) use ($expectedEntities) {
                $expectedEntity = $expectedEntities[$index];

                $this->assertEquals($expectedEntity->type, $actualEntity->type);
                $this->assertEquals($expectedEntity->value, $actualEntity->value);
                $this->assertEquals($expectedEntity->offset, $actualEntity->offset);
            });
    }

    public function textDataProvider(): array
    {
        return [
            [
                'Yes, my phone number is 123-456-7890',
                [
                    [
                        'Score' => 0.9999864101409912,
                        'Type' => 'PHONE',
                        'BeginOffset' => 24,
                        'EndOffset' => 36,
                    ],
                ],
                [
                    new PiiEntity([
                        'type' => PiiEntityTypeEnum::PHONE_NUMBER,
                        'value' => '123-456-7890',
                        'offset' => 24,
                    ]),
                ],
            ],
            [
                'Here is his email, john@example.net',
                [
                    [
                        'Score' => 0.9997759461402893,
                        'Type' => 'EMAIL',
                        'BeginOffset' => 19,
                        'EndOffset' => 35,
                    ],
                ],
                [
                    new PiiEntity([
                        'type' => PiiEntityTypeEnum::EMAIL,
                        'value' => 'john@example.net',
                        'offset' => 19,
                    ]),
                ],
            ],
            [
                'Okay, here is her phone number and email, 123-456-7890 and cindy@example.net',
                [
                    [
                        'Score' => 0.9999876022338867,
                        'Type' => 'PHONE',
                        'BeginOffset' => 42,
                        'EndOffset' => 54,
                    ],
                    [
                        'Score' => 0.9999251365661621,
                        'Type' => 'EMAIL',
                        'BeginOffset' => 59,
                        'EndOffset' => 76,
                    ],
                ],
                [
                    new PiiEntity([
                        'type' => PiiEntityTypeEnum::PHONE_NUMBER,
                        'value' => '123-456-7890',
                        'offset' => 42,
                    ]),
                    new PiiEntity([
                        'type' => PiiEntityTypeEnum::EMAIL,
                        'value' => 'cindy@example.net',
                        'offset' => 59,
                    ]),
                ],
            ],
            [
                "My son's name is John",
                [
                    [
                        'Score' => 0.9999620318412781,
                        'Type' => 'NAME',
                        'BeginOffset' => 17,
                        'EndOffset' => 27,
                    ],
                ],
                [
                    new PiiEntity([
                        'type' => PiiEntityTypeEnum::NAME,
                        'value' => 'John',
                        'offset' => 17,
                    ]),
                ],
            ],
            [
                'She lives at 1600 Amphitheatre Pkwy, Mountain View, CA 94043',
                [
                    [
                        'Score' => 0.9999668598175049,
                        'Type' => 'ADDRESS',
                        'BeginOffset' => 13,
                        'EndOffset' => 60,
                    ],
                ],
                [
                    new PiiEntity([
                        'type' => PiiEntityTypeEnum::ADDRESS,
                        'value' => '1600 Amphitheatre Pkwy, Mountain View, CA 94043',
                        'offset' => 13,
                    ]),
                ],
            ],
        ];
    }
}
