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
}
