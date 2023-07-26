<?php

namespace OnrampLab\Transcription\Redactors;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use OnrampLab\Transcription\Contracts\TextRedactor as TextRedactorContract;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Models\TranscriptSegment;
use OnrampLab\Transcription\ValueObjects\EntityText;

class TextRedactor implements TextRedactorContract
{
    /**
     * Redact the text content of transcript for PII entities.
     *
     * @param Collection<EntityText> $entityTexts
     */
    public function redact(Transcript $transcript, Collection $entityTexts): void
    {
        $entityTexts
            ->each(function (EntityText $entityText) use ($transcript) {
                $segment = $transcript->segments->get($entityText->segmentIndex);

                if (!$segment) {
                    return;
                }

                $content = $segment->content_redacted ?? $segment->content;
                $replace = Str::mask($entityText->entity->value, '*', 0);
                $length = Str::length($entityText->entity->value);
                $segment->content_redacted = substr_replace($content, $replace, $entityText->startOffset, $length);
            });

        $transcript->segments
            ->each(function (TranscriptSegment $segment) {
                if (!$segment->content_redacted) {
                    $segment->content_redacted = $segment->content;
                }
                $segment->save();
            });
    }
}
