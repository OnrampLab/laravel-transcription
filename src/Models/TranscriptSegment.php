<?php

namespace OnrampLab\Transcription\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use OnrampLab\Transcription\Database\Factories\TranscriptSegmentFactory;

class TranscriptSegment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'transcript_id',
        'start_time',
        'end_time',
        'content',
        'content_redacted',
        'words',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'words' => 'array',
    ];

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }

    public function getMatchedWords(string $value): Collection
    {
        $words = collect($this->words);
        $result = $words
            ->filter(fn (array $word) => Str::startsWith($value, preg_replace('/\p{P}$/', '', $word['content'])))
            ->map(fn (array $word, int $index) => $this->compareWithWords($value, $index, $words))
            ->sortByDesc(fn (Collection $words) => Str::length($words->pluck('content')->join(' ')))
            ->first();

        if (!$result || $result->isEmpty()) {
            throw new Exception('Provided value is not matched with any segment words.');
        }

        return $result;
    }

    private function compareWithWords(string $value, int $startIndex, Collection $words): Collection
    {
        $result = collect([$words[$startIndex]]);
        $currentIndex = $startIndex + 1;

        while ($currentIndex < $words->count()) {
            $content = $result->concat([$words[$currentIndex]])->pluck('content')->join(' ');
            $content = preg_replace('/\p{P}$/', '', $content);

            if (!Str::startsWith($value, $content)) {
                break;
            }

            $result->push($words[$currentIndex]);
            $currentIndex++;
        }

        return $result;
    }

    protected static function newFactory(): Factory
    {
        return TranscriptSegmentFactory::new();
    }
}
