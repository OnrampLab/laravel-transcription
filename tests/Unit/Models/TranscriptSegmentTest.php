<?php

namespace OnrampLab\Transcription\Tests\Unit\Models;

use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Models\TranscriptSegment;
use OnrampLab\Transcription\Tests\TestCase;

class TranscriptSegmentTest extends TestCase
{
    /**
     * @test
     */
    public function relationship_should_work(): void
    {
        $transcript = Transcript::factory()->create();
        $transcriptSegment = TranscriptSegment::factory()->create(['transcript_id' => $transcript->id]);

        $this->assertEquals($transcript->id, $transcriptSegment->transcript->id);
    }

    /**
     * @test
     */
    public function get_matched_words_should_work(): void
    {
        $transcriptSegment = TranscriptSegment::factory()->create([
            'content' => 'You can use amazon transcribe as a standalone transcription service.',
            'words' => [
                [
                    'start_time' => '00:00:02',
                    'end_time' => '00:00:02',
                    'content' => 'You',
                ],
                [
                    'start_time' => '00:00:02',
                    'end_time' => '00:00:02',
                    'content' => 'can',
                ],
                [
                    'start_time' => '00:00:02',
                    'end_time' => '00:00:02',
                    'content' => 'use',
                ],
                [
                    'start_time' => '00:00:02',
                    'end_time' => '00:00:03',
                    'content' => 'amazon',
                ],
                [
                    'start_time' => '00:00:03',
                    'end_time' => '00:00:03',
                    'content' => 'transcribe',
                ],
                [
                    'start_time' => '00:00:03',
                    'end_time' => '00:00:04',
                    'content' => 'as',
                ],
                [
                    'start_time' => '00:00:04',
                    'end_time' => '00:00:04',
                    'content' => 'a',
                ],
                [
                    'start_time' => '00:00:04',
                    'end_time' => '00:00:04',
                    'content' => 'standalone',
                ],
                [
                    'start_time' => '00:00:04',
                    'end_time' => '00:00:05',
                    'content' => 'transcription',
                ],
                [
                    'start_time' => '00:00:05',
                    'end_time' => '00:00:05',
                    'content' => 'service',
                ],
            ]
        ]);
        $value = 'amazon transcribe';
        $actualResult = $transcriptSegment->getMatchedWords($value);
        $expectedResult = collect([$transcriptSegment->words[3], $transcriptSegment->words[4]]);

        $this->assertEquals($actualResult->count(), 2);
        $this->assertEquals($actualResult[0]['content'], $expectedResult[0]['content']);
        $this->assertEquals($actualResult[1]['content'], $expectedResult[1]['content']);
    }
}
