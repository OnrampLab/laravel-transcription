<?php

namespace OnrampLab\Transcription;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use OnrampLab\Transcription\Contracts\TranscriptionManager as TranscriptionManagerContract;
use OnrampLab\Transcription\Contracts\TranscriptionProvider;

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
