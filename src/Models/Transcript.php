<?php

namespace OnrampLab\Transcription\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use OnrampLab\Transcription\Database\Factories\TranscriptFactory;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\ValueObjects\TranscriptChunk;
use OnrampLab\Transcription\ValueObjects\TranscriptChunkSection;

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
        'is_redacted',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string>
     */
    protected $casts = [
        'is_redacted' => 'boolean',
    ];

    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class);
    }

    public function getSegmentsChunk(int $size = 5, string $glue = "\n"): Collection
    {
        return $this->segments
            ->chunk($size)
            ->map(function (Collection $segments) use ($glue) {
                $contents = collect([]);
                $sections = $segments->reduce(
                    function (Collection $sections, TranscriptSegment $currentSegment, int $index) use (&$contents) {
                        $startOffset = $index > 0 ? $sections[$index - 1]->endOffset + 1 : 0;
                        $endOffset = $startOffset + strlen($currentSegment->content);
                        $sections->push(new TranscriptChunkSection([
                            'segment' => $currentSegment,
                            'start_offset' => $startOffset,
                            'end_offset' => $endOffset,
                        ]));
                        $contents->push($currentSegment->content);

                        return $sections;
                    },
                    collect([]),
                );

                return new TranscriptChunk([
                    'content' => $contents->join($glue),
                    'sections' => $sections,
                ]);
            });
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
