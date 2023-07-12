<?php

namespace OnrampLab\Transcription\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OnrampLab\Transcription\Database\Factories\TranscriptSegmentFactory;

class TranscriptSegment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'transcript_id',
        'start_time',
        'end_time',
        'content',
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

    protected static function newFactory(): Factory
    {
        return TranscriptSegmentFactory::new();
    }
}
