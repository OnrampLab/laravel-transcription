<?php

namespace OnrampLab\Transcription;

use Closure;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OnrampLab\Transcription\Contracts\AudioTranscriber;
use OnrampLab\Transcription\Contracts\Callbackable;
use OnrampLab\Transcription\Contracts\Confirmable;
use OnrampLab\Transcription\Contracts\PiiEntityDetector;
use OnrampLab\Transcription\Contracts\TextRedactor as TextRedactorContract;
use OnrampLab\Transcription\Contracts\TranscriptionManager as TranscriptionManagerContract;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\Events\TranscriptCompletedEvent;
use OnrampLab\Transcription\Events\TranscriptFailedEvent;
use OnrampLab\Transcription\Jobs\ConfirmTranscriptionJob;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Redactors\TextRedactor;
use OnrampLab\Transcription\ValueObjects\EntityAudio;
use OnrampLab\Transcription\ValueObjects\EntityText;
use OnrampLab\Transcription\ValueObjects\PiiEntity;
use OnrampLab\Transcription\ValueObjects\TranscriptChunk;
use OnrampLab\Transcription\ValueObjects\TranscriptChunkSection;
use OnrampLab\Transcription\ValueObjects\Transcription;

class TranscriptionManager implements TranscriptionManagerContract
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * The array of resolved audio transcribers.
     */
    protected array $transcribers = [];

    /**
     * The array of resolved PII entity detectors.
     */
    protected array $detectors = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Add a audio transcriber resolver.
     */
    public function addTranscriber(string $driverName, Closure $resolver): void
    {
        $this->transcribers[$driverName] = $resolver;
    }

    /**
     * Add a PII entity detector resolver.
     */
    public function addDetector(string $driverName, Closure $resolver): void
    {
        $this->detectors[$driverName] = $resolver;
    }

    /**
     * Make transcription for audio file in specific language
     */
    public function make(string $audioUrl, string $languageCode, ?bool $shouldRedact = false, ?string $transcriberName = null): Transcript
    {
        $type = Str::kebab(Str::camel($transcriberName ?: $this->getDefaultProcessor('transcription')));
        $transcriber = $this->resolveTranscriber($transcriberName);

        if ($transcriber instanceof Callbackable) {
            $transcriber->setUp('POST', URL::route('transcription.callback', ['type' => $type]));
        }

        $transcription = $transcriber->transcribe($audioUrl, $languageCode);

        $transcript = Transcript::create([
            'type' => $type,
            'external_id' => $transcription->id,
            'status' => $transcription->status->value,
            'audio_file_url' => $audioUrl,
            'language_code' => $languageCode,
            'is_redacted' => $shouldRedact,
        ]);

        if ($transcriber instanceof Confirmable) {
            ConfirmTranscriptionJob::dispatch($transcript->type, $transcript->external_id)
                ->delay(now()->addSeconds(config('transcription.confirmation.interval')));
        }

        return $transcript;
    }

    /**
     * Confirm asynchronous transcription process
     */
    public function confirm(string $type, string $externalId): Transcript
    {
        $transcriberName = Str::snake(Str::camel($type));
        $transcriber = $this->resolveTranscriber($transcriberName);

        if (! $transcriber instanceof Confirmable) {
            throw new Exception("The [{$transcriberName}] audio transcriber is not confirmable.");
        }

        $transcript = Transcript::where('type', $type)
            ->where('external_id', $externalId)
            ->firstOrFail();
        $transcription = $transcriber->fetch($externalId);
        $transcript = $this->parse($transcription, $transcript, $transcriber);

        $this->triggerEvent($transcript);

        return $transcript;
    }

    /**
     * Execute asynchronous transcription callback
     */
    public function callback(string $type, array $requestHeader, array $requestBody): Transcript
    {
        $transcriberName = Str::snake(Str::camel($type));
        $transcriber = $this->resolveTranscriber($transcriberName);

        if (! $transcriber instanceof Callbackable) {
            throw new Exception("The [{$transcriberName}] audio transcriber is not callbackable.");
        }

        $transcriber->validate($requestHeader, $requestBody);

        $transcription = $transcriber->process($requestHeader, $requestBody);
        $transcript = Transcript::where('type', $type)
            ->where('external_id', $transcription->id)
            ->firstOrFail();
        $transcript = $this->parse($transcription, $transcript, $transcriber);

        $this->triggerEvent($transcript);

        return $transcript;
    }

    /**
     * Redact personally identifiable information (PII) content within transcript
     */
    public function redact(Transcript $transcript, ?string $detectorName = null): void
    {
        if (! $transcript->is_redacted) {
            return;
        }

        $detector = $this->resolveDetector($detectorName);
        $languageCode = $transcript->language_code;
        $entityTexts = collect([]);
        $entityAudios = collect([]);
        $chunkSize = 5;
        $transcript->getSegmentsChunk($chunkSize)
            ->each(function (TranscriptChunk $chunk, int $chunkIndex) use ($detector, $languageCode, $chunkSize, &$entityTexts, &$entityAudios) {
                $segmentBase = $chunkIndex * $chunkSize;
                $entities = collect($detector->detect($chunk->content, $languageCode));
                $entities->each(function (PiiEntity $entity) use (&$chunk, $segmentBase, &$entityTexts, &$entityAudios) {
                    $sectionIndex = $chunk->search($entity->offset);
                    $section = $chunk->get($sectionIndex);
                    $entityTexts->push(new EntityText([
                        'entity' => $entity,
                        'segment_index' => $segmentBase + $sectionIndex,
                        'start_offset' => $entity->offset - $section->startOffset,
                        'end_offset' => $entity->offset - $section->startOffset + strlen($entity->value),
                    ]));
                    $segment = $section->segment;
                    $words = $segment->getMatchedWords($entity->value);
                    $entityAudios->push(new EntityAudio([
                        'entity' => $entity,
                        'start_time' => $words->first()['start_time'],
                        'end_time' => $words->last()['end_time'],
                    ]));
                });
            });
        $textRedactor = $this->resolveTextRedactor();
        $textRedactor->redact($transcript, $entityTexts);
    }

    /**
     * Parse transcription object and update result to transcript model.
     */
    protected function parse(Transcription $transcription, Transcript $transcript, AudioTranscriber $transcriber): Transcript
    {
        if ($transcription->status === TranscriptionStatusEnum::COMPLETED) {
            $transcriber->parse($transcription, $transcript);
        }

        $transcript->status = $transcription->status->value;
        $transcript->save();

        return $transcript;
    }

    /**
     * Trigger transcript related event.
     */
    protected function triggerEvent(Transcript $transcript): void
    {
        $eventClass = match ($transcript->status) {
            TranscriptionStatusEnum::COMPLETED->value => TranscriptCompletedEvent::class,
            TranscriptionStatusEnum::FAILED->value => TranscriptFailedEvent::class,
            default => null,
        };

        if (! $eventClass) {
            return;
        }

        event(App::make($eventClass, ['transcript' => $transcript]));
    }

    /**
     * Resolve a audio transcriber.
     */
    protected function resolveTranscriber(?string $transcriberName): AudioTranscriber
    {
        $config = $this->getProcessorConfig('transcription', 'transcriber', $transcriberName);
        $driverName = $config['driver'];

        if (! isset($this->transcribers[$driverName])) {
            throw new InvalidArgumentException("No audio transcriber with [{$driverName}] driver.");
        }

        return call_user_func($this->transcribers[$driverName], $config);
    }

    /**
     * Resolve a PII entity detector.
     */
    protected function resolveDetector(?string $detectorName): PiiEntityDetector
    {
        $config = $this->getProcessorConfig('redaction', 'detector', $detectorName);
        $driverName = $config['driver'];

        if (! isset($this->detectors[$driverName])) {
            throw new InvalidArgumentException("No redaction detector with [{$driverName}] driver.");
        }

        return call_user_func($this->detectors[$driverName], $config);
    }

    /**
     * Resolve a text redactor.
     */
    protected function resolveTextRedactor(): TextRedactorContract
    {
        return $this->app->make(TextRedactor::class);
    }

    /**
     * Get the processor configuration.
     */
    protected function getProcessorConfig(string $featureName, string $processorType, ?string $processorName): array
    {
        $processorName = $processorName ?: $this->getDefaultProcessor($featureName);
        $config = $this->app['config']["transcription.{$featureName}.{$processorType}s.{$processorName}"] ?? null;

        if (is_null($config)) {
            throw new InvalidArgumentException("The [{$processorName}] {$featureName} {$processorType} has not been configured.");
        }

        return $config;
    }

    /**
     * Get the name of default processor.
     */
    protected function getDefaultProcessor(string $featureName): string
    {
        return $this->app['config']["transcription.{$featureName}.default"] ?? '';
    }
}
