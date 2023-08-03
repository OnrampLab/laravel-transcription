<?php

namespace OnrampLab\Transcription\Tests\Unit\Models;

use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Models\TranscriptSegment;
use OnrampLab\Transcription\Tests\TestCase;

class TranscriptTest extends TestCase
{
    /**
     * @test
     */
    public function relationship_should_work(): void
    {
        $transcript = Transcript::factory()->create();
        $transcriptSegment = TranscriptSegment::factory()->create(['transcript_id' => $transcript->id]);

        $this->assertEquals($transcriptSegment->id, $transcript->segments->first()->id);
    }

    /**
     * @test
     */
    public function get_segments_chunk_should_work(): void
    {
        $transcript = Transcript::factory()->create();
        $segment1 = TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'content' => 'Hi, this is Eric.',
        ]);
        $segment2 = TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'content' => 'Is John there?',
        ]);
        $segment3 = TranscriptSegment::factory()->create([
            'transcript_id' => $transcript->id,
            'content' => 'No, you guys call the wrong number.',
        ]);
        $size = 2;
        $glue = "\n";

        $chunks = $transcript->getSegmentsChunk($size, $glue);

        $this->assertEquals($chunks[0]->content, $segment1->content . $glue . $segment2->content);
        $this->assertEquals($chunks[0]->sections[0]->segment->id, $segment1->id);
        $this->assertEquals($chunks[0]->sections[0]->startOffset, 0);
        $this->assertEquals($chunks[0]->sections[0]->endOffset, 17);
        $this->assertEquals($chunks[0]->sections[1]->segment->id, $segment2->id);
        $this->assertEquals($chunks[0]->sections[1]->startOffset, 18);
        $this->assertEquals($chunks[0]->sections[1]->endOffset, 32);
        $this->assertEquals($chunks[1]->content, $segment3->content);
        $this->assertEquals($chunks[1]->sections[0]->segment->id, $segment3->id);
        $this->assertEquals($chunks[1]->sections[0]->startOffset, 0);
        $this->assertEquals($chunks[1]->sections[0]->endOffset, 35);

    }
}
