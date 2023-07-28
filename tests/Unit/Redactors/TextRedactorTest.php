<?php

namespace OnrampLab\Transcription\Tests\Unit\Redactors;

use OnrampLab\Transcription\Enums\PiiEntityTypeEnum;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Models\TranscriptSegment;
use OnrampLab\Transcription\Redactors\TextRedactor;
use OnrampLab\Transcription\Tests\TestCase;
use OnrampLab\Transcription\ValueObjects\EntityText;
use OnrampLab\Transcription\ValueObjects\PiiEntity;

class TextRedactorTest extends TestCase
{
    private TextRedactor $redactor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redactor = $this->app->make(TextRedactor::class);
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

        $entityTexts = collect([
            new EntityText([
                'entity' => new PiiEntity([
                    'type' => PiiEntityTypeEnum::NAME,
                    'value' => 'Eric',
                    'offset' => 12,
                ]),
                'segment_index' => 0,
                'start_offset' => 12,
                'end_offset' => 16,
            ]),
            new EntityText([
                'entity' => new PiiEntity([
                    'type' => PiiEntityTypeEnum::PHONE_NUMBER,
                    'value' => '123-456-7890',
                    'offset' => 112,
                ]),
                'segment_index' => 2,
                'start_offset' => 57,
                'end_offset' => 69,
            ]),
        ]);

        $this->redactor->redact($transcript, $entityTexts);

        $transcript->unsetRelations();

        $this->assertEquals($transcript->segments[0]->content_redacted, 'Hi, this is **** from Fake Service.');
        $this->assertEquals($transcript->segments[1]->content_redacted, 'How are you doing?');
        $this->assertEquals($transcript->segments[2]->content_redacted, 'Yeah, but you guys call the wrong number. This number is ************');
    }
}
