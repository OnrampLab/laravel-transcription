<?php

namespace OnrampLab\Transcription;

use Closure;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OnrampLab\Transcription\Contracts\Callbackable;
use OnrampLab\Transcription\Contracts\Confirmable;
use OnrampLab\Transcription\Contracts\TranscriptionManager as TranscriptionManagerContract;
use OnrampLab\Transcription\Contracts\TranscriptionProvider;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\Jobs\ConfirmTranscriptionJob;
use OnrampLab\Transcription\Models\Transcript;
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
     * Make transcription for audio file in specific language
     */
    public function make(string $audioUrl, string $languageCode, ?string $providerName = null): void
    {
        $type = Str::kebab(Str::camel($providerName ?: $this->getDefaultProvider()));
        $provider = $this->resolveProvider($providerName);
        $transcription = $provider->transcribe($audioUrl, $languageCode);

        $transcript = Transcript::create([
            'type' => $type,
            'external_id' => $transcription->id,
            'status' => $transcription->status->value,
            'audio_file_url' => $audioUrl,
            'language_code' => $languageCode,
        ]);

        if ($provider instanceof Confirmable) {
            ConfirmTranscriptionJob::dispatch($transcript->type, $transcript->external_id)
                ->delay(now()->addSeconds(config('transcription.confirmation.interval')));
        }
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

        return $this->parse($transcription, $transcript, $provider);
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

        return $this->parse($transcription, $transcript, $provider);
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
     * Resolve a transcription provider.
     */
    protected function resolveProvider(?string $providerName): TranscriptionProvider
    {
        $config = $this->getProviderConfig($providerName);
        $name = $config['driver'];

        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException("No transcription provider for [{$name}].");
        }

        return call_user_func($this->providers[$name], $config);
    }

    /**
     * Get the transcription provider configuration.
     */
    protected function getProviderConfig(?string $providerName): array
    {
        $name = $providerName ?: $this->getDefaultProvider();
        $config = $this->app['config']["transcription.providers.{$name}"] ?? null;

        if (is_null($config)) {
            throw new InvalidArgumentException("The [{$name}] transcription provider has not been configured.");
        }

        return $config;
    }

    /**
     * Get the name of default transcription provider.
     */
    protected function getDefaultProvider(): string
    {
        return $this->app['config']['transcription.default'] ?? '';
    }
}
