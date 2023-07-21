<?php

namespace OnrampLab\Transcription\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use OnrampLab\Transcription\Models\Transcript;

class TranscriptFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string|null
     */
    protected $model = Transcript::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'type' => $this->faker->word(),
            'external_id' => $this->faker->uuid(),
            'status' => 'processing',
            'audio_file_url' => $this->faker->url(),
            'language_code' => $this->faker->languageCode(),
            'is_redacted' => $this->faker->boolean(),
        ];
    }
}
