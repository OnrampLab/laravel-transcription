<?php

namespace OnrampLab\Transcription\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OnrampLab\Transcription\Database\Factories\TranscriptFactory;

class Transcript extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'external_id',
        'status',
        'audio_file_url',
        'language_code',
    ];

    protected static function newFactory(): Factory
    {
        return TranscriptFactory::new();
    }
}
