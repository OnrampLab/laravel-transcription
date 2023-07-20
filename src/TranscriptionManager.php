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
use OnrampLab\Transcription\Contracts\Callbackable;
use OnrampLab\Transcription\Contracts\Confirmable;
use OnrampLab\Transcription\Contracts\PiiEntityDetector;
use OnrampLab\Transcription\Contracts\TranscriptionManager as TranscriptionManagerContract;
use OnrampLab\Transcription\Contracts\TranscriptionProvider;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\Events\TranscriptCompletedEvent;
use OnrampLab\Transcription\Events\TranscriptFailedEvent;
use OnrampLab\Transcription\Jobs\ConfirmTranscriptionJob;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Models\TranscriptSegment;
use OnrampLab\Transcription\ValueObjects\PiiEntity;
use OnrampLab\Transcription\ValueObjects\Transcription;

class TranscriptionManager implements TranscriptionManagerContract
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * The array of resolved transcription providers.
     */
    protected array $providers = [];

    /**
     * The array of resolved PII entity detectors.
     */
    protected array $detectors = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Add a transcription provider resolver.
     */
    public function addProvider(string $driverName, Closure $resolver): void
    {
        $this->providers[$driverName] = $resolver;
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
    public function make(string $audioUrl, string $languageCode, ?bool $shouldRedact = false, ?string $providerName = null): Transcript
    {
        $type = Str::kebab(Str::camel($providerName ?: $this->getDefaultProcessor('transcription')));
        $provider = $this->resolveProvider($providerName);

        if ($provider instanceof Callbackable) {
            $provider->setUp('POST', URL::route('transcription.callback', ['type' => $type]));
        }

        $transcription = $provider->transcribe($audioUrl, $languageCode);

        $transcript = Transcript::create([
            'type' => $type,
            'external_id' => $transcription->id,
            'status' => $transcription->status->value,
            'audio_file_url' => $audioUrl,
            'language_code' => $languageCode,
            'is_redacted' => $shouldRedact,
        ]);

        if ($provider instanceof Confirmable) {
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
        $providerName = Str::snake(Str::camel($type));
        $provider = $this->resolveProvider($providerName);

        if (!$provider instanceof Confirmable) {
            throw new Exception("The [{$providerName}] transcription provider is not confirmable.");
        }

        $transcript = Transcript::where('type', $type)
            ->where('external_id', $externalId)
            ->firstOrFail();
        $transcription = $provider->fetch($externalId);
        $transcript = $this->parse($transcription, $transcript, $provider);

        $this->triggerEvent($transcript);

        return $transcript;
    }

    /**
     * Execute asynchronous transcription callback
     */
    public function callback(string $type, array $requestHeader, array $requestBody): Transcript
    {
        $providerName = Str::snake(Str::camel($type));
        $provider = $this->resolveProvider($providerName);

        if (!$provider instanceof Callbackable) {
            throw new Exception("The [{$providerName}] transcription provider is not callbackable.");
        }

        $provider->validate($requestHeader, $requestBody);

        $transcription = $provider->process($requestHeader, $requestBody);
        $transcript = Transcript::where('type', $type)
            ->where('external_id', $transcription->id)
            ->firstOrFail();
        $transcript = $this->parse($transcription, $transcript, $provider);

        $this->triggerEvent($transcript);

        return $transcript;
    }

    /**
     * Redact personally identifiable information (PII) content within transcript
     */
    public function redact(Transcript $transcript, ?string $detectorName = null): void
    {
        if (!$transcript->is_redacted) {
            return;
        }

        $detector = $this->resolveDetector($detectorName);
        $languageCode = $transcript->language_code;
        $transcript->segments
            ->chunk(5)
            ->each(function (Collection $segments) use ($detector, $languageCode) {
                $contents = collect([]);
                $segments->reduce(
                    function (?TranscriptSegment $previousSegment, TranscriptSegment $currentSegment) use (&$contents) {
                        $offset = $previousSegment ? $previousSegment->end_offset + 1 : 0;
                        $currentSegment->start_offset = $offset;
                        $currentSegment->end_offset = $offset + strlen($currentSegment->content);

                        $contents->push($currentSegment->content);
                        $currentSegment->content_redacted = $currentSegment->content;

                        return $currentSegment;
                    },
                    null,
                );
                $entities = collect($detector->detect($contents->join("\n"), $languageCode));
                $entities->each(function (PiiEntity $entity) use (&$segments) {
                    $segment = $segments->first(fn (TranscriptSegment $segment) => $entity->offset >= $segment->start_offset && $entity->offset < $segment->end_offset);
                    $segment->content_redacted = str_replace($entity->value, Str::mask($entity->value, '*', 0), $segment->content_redacted);
                });
                $segments->each(function (TranscriptSegment $segment) {
                    $segment->offsetUnset('start_offset');
                    $segment->offsetUnset('end_offset');
                    $segment->save();
                });
            });
    }

    /**
     * Parse transcription object and update result to transcript model.
     */
    protected function parse(Transcription $transcription, Transcript $transcript, TranscriptionProvider $provider): Transcript
    {
        if ($transcription->status === TranscriptionStatusEnum::COMPLETED) {
            $provider->parse($transcription, $transcript);
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

        if (!$eventClass) {
            return;
        }

        event(App::make($eventClass, ['transcript' => $transcript]));
    }

    /**
     * Resolve a transcription provider.
     */
    protected function resolveProvider(?string $providerName): TranscriptionProvider
    {
        $config = $this->getProcessorConfig('transcription', 'provider', $providerName);
        $driverName = $config['driver'];

        if (! isset($this->providers[$driverName])) {
            throw new InvalidArgumentException("No transcription provider with [{$driverName}] driver.");
        }

        return call_user_func($this->providers[$driverName], $config);
    }

    /**
     * Resolve a PII entity detector.
     */
    protected function resolveDetector(?string $providerName): PiiEntityDetector
    {
        $config = $this->getProcessorConfig('redaction', 'detector', $providerName);
        $driverName = $config['driver'];

        if (! isset($this->detectors[$driverName])) {
            throw new InvalidArgumentException("No redaction detector with [{$driverName}] driver.");
        }

        return call_user_func($this->detectors[$driverName], $config);
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
