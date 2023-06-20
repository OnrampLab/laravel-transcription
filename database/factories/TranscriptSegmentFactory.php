<?php

namespace OnrampLab\Transcription\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Models\TranscriptSegment;

class TranscriptSegmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string|null
     */
    protected $model = TranscriptSegment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'transcript_id'=> Transcript::factory(),
            'start_time'=> $this->faker->time(),
            'end_time'=> $this->faker->time(),
            'content'=> $this->faker->sentence(),
            'words'=> [],
        ];
    }
}
