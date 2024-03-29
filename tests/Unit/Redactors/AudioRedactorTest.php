<?php

namespace OnrampLab\Transcription\Tests\Unit\Redactors;

use FFMpeg\FFMpeg;
use FFMpeg\Filters\Audio\AudioFilters;
use FFMpeg\Media\Audio;
use GuzzleHttp\Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use OnrampLab\Transcription\Enums\PiiEntityTypeEnum;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Models\TranscriptSegment;
use OnrampLab\Transcription\Redactors\AudioRedactor;
use OnrampLab\Transcription\Tests\TestCase;
use OnrampLab\Transcription\ValueObjects\EntityAudio;
use OnrampLab\Transcription\ValueObjects\PiiEntity;

class AudioRedactorTest extends TestCase
{
    private MockInterface $localDiskMock;

    private MockInterface $audioDiskMock;

    private MockInterface $audioMock;

    private MockInterface $audioFiltersMock;

    private MockInterface $ffmpegMock;

    private MockInterface $httpClientMock;

    private AudioRedactor $redactor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->localDiskMock = Mockery::mock(Filesystem::class);
        $this->audioDiskMock = Mockery::mock(Filesystem::class);

        $this->httpClientMock = $this->mock(Client::class);

        Storage::shouldReceive('disk')
            ->twice()
            ->with('local')
            ->andReturn($this->localDiskMock);

        $this->audioMock = Mockery::mock(Audio::class);
        $this->audioFiltersMock = Mockery::mock(AudioFilters::class);
        $this->ffmpegMock = Mockery::mock('overload:' . FFMpeg::class);
        $this->ffmpegMock
            ->shouldReceive('create')
            ->once()
            ->andReturnSelf();

        $this->redactor = $this->app->make(AudioRedactor::class);
    }

    /**
     * @test
     */
    public function redact_should_work(): void
    {
        $transcript = Transcript::factory()->create();

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

        $entityAudios = collect([
            new EntityAudio([
                'entity' => new PiiEntity([
                    'type' => PiiEntityTypeEnum::NAME,
                    'value' => 'Eric',
                    'offset' => 12,
                ]),
                'start_time' => '00.00.00.235',
                'end_time' => '00.00.01.337',
            ]),
            new EntityAudio([
                'entity' => new PiiEntity([
                    'type' => PiiEntityTypeEnum::PHONE_NUMBER,
                    'value' => '123-456-7890',
                    'offset' => 112,
                ]),
                'start_time' => '00.00.07.651',
                'end_time' => '00.00.08.932',
            ]),
        ]);

        $tempFilePath = '/fake/temp/file/path';
        $outputFilePath = '/fake/output/file/path';
        $audioContent = 'fake audio file content';
        $audioFolder = 'fake/audio/folder';
        $audioUrl = 'https://www.example.com/audio/redacted.wav';

        $this->httpClientMock
            ->shouldReceive('request')
            ->once()
            ->with('GET', $transcript->audio_file_url, ['sink' => $tempFilePath]);

        $this->ffmpegMock
            ->shouldReceive('open')
            ->once()
            ->with($tempFilePath)
            ->andReturn($this->audioMock);

        $this->audioFiltersMock
            ->shouldReceive('custom')
            ->once()
            ->withArgs(function (string $parameters) {
                $expectedParams = [
                    "volume=enable='between(t,0.235,1.337)':volume=0",
                    "volume=enable='between(t,7.651,8.932)':volume=0",
                ];

                return $parameters === implode(',', $expectedParams);
            });

        $this->audioMock
            ->shouldReceive('filters')
            ->once()
            ->andReturn($this->audioFiltersMock);

        $this->audioMock
            ->shouldReceive('save')
            ->once()
            ->withArgs(fn ($format, $path) => $path === $outputFilePath);

        $this->localDiskMock
            ->shouldReceive('path')
            ->times(3)
            ->andReturnValues([$tempFilePath, $tempFilePath, $outputFilePath]);

        $this->localDiskMock
            ->shouldReceive('get')
            ->once()
            ->andReturn($audioContent);

        $this->localDiskMock
            ->shouldReceive('delete')
            ->twice();

        $this->audioDiskMock
            ->shouldReceive('put')
            ->once()
            ->withArgs(fn ($path, $contents) => $contents === $audioContent);

        $this->audioDiskMock
            ->shouldReceive('url')
            ->once()
            ->andReturn($audioUrl);

        $this->redactor->redact($transcript, $entityAudios, $this->audioDiskMock, $audioFolder);

        $transcript->refresh();

        $this->assertEquals($transcript->audio_file_url_redacted, $audioUrl);
    }
}
