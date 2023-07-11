<?php

namespace OnrampLab\Transcription\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OnrampLab\Transcription\Database\Factories\TranscriptFactory;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;

class Transcript extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'type',
        'external_id',
        'status',
        'audio_file_url',
        'language_code',
    ];

    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class);
    }

    public function isProcessing(): bool
    {
        return $this->status === TranscriptionStatusEnum::PROCESSING->value;
    }

    public function isCompleted(): bool
    {
        return $this->status === TranscriptionStatusEnum::COMPLETED->value;
    }

    public function isFailed(): bool
    {
        return $this->status === TranscriptionStatusEnum::FAILED->value;
    }

    public function isFinished(): bool
    {
        return $this->isCompleted() || $this->isFailed();
    }

    protected static function newFactory(): Factory
    {
        return TranscriptFactory::new();
    }
}
