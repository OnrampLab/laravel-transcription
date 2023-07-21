<?php

namespace OnrampLab\Transcription\PiiEntityDetectors;

use Aws\Comprehend\ComprehendClient;
use Aws\Credentials\Credentials;
use Illuminate\Support\Collection;
use OnrampLab\Transcription\Contracts\PiiEntityDetector;
use OnrampLab\Transcription\Enums\PiiEntityTypeEnum;
use OnrampLab\Transcription\ValueObjects\PiiEntity;

class AwsComprehendPiiEntityDetector implements PiiEntityDetector
{
    private const ENTITY_TYPE_MAPPING = [
        'PHONE' => PiiEntityTypeEnum::PHONE_NUMBER,
        'EMAIL' => PiiEntityTypeEnum::EMAIL,
        'NAME' => PiiEntityTypeEnum::NAME,
        'ADDRESS' => PiiEntityTypeEnum::ADDRESS,
    ];

    protected ComprehendClient $client;

    public function __construct(array $config)
    {
        $this->client = new ComprehendClient([
            'version' => '2017-11-27',
            'region' => $config['region'],
            'credentials' => new Credentials($config['access_key'], $config['access_secret']),
        ]);
    }

    /**
     * Detect the input text for entities that contain personally identifiable information (PII).
     *
     * @return array<PiiEntity>
     */
    public function detect(string $text, string $languageCode): array
    {
        $languageCode = explode('-', $languageCode)[0];
        $result = $this->client->detectPiiEntities([
            'LanguageCode' => $languageCode,
            'Text' => $text,
        ]);

        return Collection::make($result['Entities'])
            ->filter(fn (array $entity) => isset(self::ENTITY_TYPE_MAPPING[$entity['Type']]))
            ->map(function (array $entity) use ($text) {
                $type = self::ENTITY_TYPE_MAPPING[$entity['Type']];
                $value = substr($text, $entity['BeginOffset'], $entity['EndOffset'] - $entity['BeginOffset']);

                return new PiiEntity([
                    'type' => $type,
                    'value' => $value,
                    'offset' => $entity['BeginOffset'],
                ]);
            })
            ->values()
            ->all();
    }
}
