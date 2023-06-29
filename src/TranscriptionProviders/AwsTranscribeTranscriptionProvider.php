<?php

namespace OnrampLab\Transcription\TranscriptionProviders;

use Aws\Credentials\Credentials;
use Aws\TranscribeService\TranscribeServiceClient;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OnrampLab\Transcription\Contracts\TranscriptionProvider;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Models\TranscriptSegment;
use OnrampLab\Transcription\ValueObjects\Transcription;

class AwsTranscribeTranscriptionProvider implements TranscriptionProvider
{
    protected TranscribeServiceClient $client;

    protected array $tags;

    private const JOB_STATUS_MAPPING = [
        'QUEUED' => TranscriptionStatusEnum::PROCESSING,
        'IN_PROGRESS' => TranscriptionStatusEnum::PROCESSING,
        'FAILED' => TranscriptionStatusEnum::FAILED,
        'COMPLETED' => TranscriptionStatusEnum::COMPLETED,
    ];

    public function __construct(array $config)
    {
        $this->client = new TranscribeServiceClient([
            'version' => '2017-10-26',
            'region' => $config['region'],
            'credentials' => new Credentials($config['access_key'], $config['access_secret']),
        ]);
        $this->tags = $config['tags'];
    }

    /**
     * Transcribe audio file into text records in specific language.
     */
    public function transcribe(string $audioUrl, string $languageCode): Transcription
    {
        $this->validateUrl($audioUrl);

        if (Str::startsWith($audioUrl, 'https://')) {
            $audioUrl = $this->convertUrl($audioUrl, 'https', 's3');
        }

        $id = Str::uuid()->toString();
        $job = $this->client->startTranscriptionJob([
            'TranscriptionJobName' => $id,
            'Media' => [
                'MediaFileUri' => $audioUrl,
            ],
            'Tags' => $this->convertTags($this->tags),
        ]);
        $job = $job['TranscriptionJob'];

        return new Transcription([
            'id' => $id,
            'status' => self::JOB_STATUS_MAPPING[$job['TranscriptionJobStatus']],
        ]);
    }

    /**
     * Fetch transcription data from third-party service.
     */
    public function fetch(string $id): Transcription
    {
        $job = $this->client->getTranscriptionJob([
            'TranscriptionJobName' => $id,
        ]);
        $job = $job['TranscriptionJob'];
        $status = self::JOB_STATUS_MAPPING[$job['TranscriptionJobStatus']];
        $result = null;

        if ($status === TranscriptionStatusEnum::COMPLETED) {
            $transcriptUrl = $job['Transcript']['TranscriptFileUri'];

            if (Str::startsWith($transcriptUrl, 's3://')) {
                $transcriptUrl = $this->convertUrl($transcriptUrl, 's3', 'https');;
            }

            /** @var GuzzleClient $client */
            $client = App::make(GuzzleClient::class);
            $response = $client->request('GET', $transcriptUrl);
            $result = json_decode($response->getBody()->getContents(), true);
        }

        return new Transcription([
            'id' => $id,
            'status' => $status,
            'result' => $result,
        ]);
    }

    private function validateUrl(string $url): void
    {
        if (Str::startsWith($url, 's3://')) {
            return;
        }

        if (Str::startsWith($url, 'https://') && Str::contains($url, 's3.amazonaws.com')) {
            return;
        }

        throw new InvalidArgumentException('Provided URL is not a valid Amazon S3 bucket URL');
    }

    private function convertUrl(string $url, string $sourceSchema, $targetSchema): string
    {
        $pattern = $this->getUrlPattern($sourceSchema);
        $isMatched = preg_match($pattern, $url, $matches);

        if (!$isMatched) {
            throw new InvalidArgumentException('Provided URL is not a valid Amazon S3 bucket URL');
        }

        $bucketName = $matches[1];
        $result = Str::replace($this->getUrlBaseName($sourceSchema, $bucketName), $this->getUrlBaseName($targetSchema, $bucketName), $url);

        return $result;
    }

    private function getUrlPattern(string $schema): string
    {
        return match ($schema) {
            'https' => '/^https:\/\/(.+?).s3.amazonaws.com\//',
            's3' => '/^s3:\/\/(.+?)\//',
            default => throw new Exception("Unsupported URL schema: {$schema}"),
        };
    }

    private function getUrlBaseName(string $schema, string $bucketName): string
    {
        $hostName = match ($schema) {
            'https' => $bucketName . '.s3.amazonaws.com',
            's3' => $bucketName,
            default => throw new Exception("Unsupported URL schema: {$schema}"),
        };

        return "{$schema}://{$hostName}";
    }

    private function convertTags(array $tags): array
    {
        return Collection::make($tags)
            ->map(fn (string $value, string $key) => [
                'Key' => $key,
                'Value' => $value,
            ])
            ->values()
            ->toArray();
    }


    /**
     * Parse transcripts result of transcription and persist them into database.
     */
    public function parse(Transcription $transcription, Transcript $transcript): void
    {
        $words = Collection::make([]);

        Collection::make($transcription->result['results']['items'])
            ->each(function (array $item) use (&$words, $transcript) {
                $type = $item['type'];
                $words = match ($item['type']) {
                    'pronunciation' => $this->parsePronunciation($item, $words, $transcript),
                    'punctuation' => $this->parsePunctuation($item, $words, $transcript),
                    default => throw new Exception("Unknown item type: {$type}"),
                };
            });
    }

    private function parsePronunciation(array $item, Collection $words, Transcript $transcript): Collection
    {
        return $words->concat([
            [
                'start_time' => gmdate('H:i:s', (int) $item['start_time']),
                'end_time' => gmdate('H:i:s', (int) $item['end_time']),
                'content' => $item['alternatives'][0]['content'],
            ],
        ]);
    }

    private function parsePunctuation(array $item, Collection $words, Transcript $transcript): Collection
    {
        $first = $words->first();
        $last = $words->last();
        $last['content'] = $last['content'] . $item['alternatives'][0]['content'];
        $words[$words->count() - 1] = $last;

        TranscriptSegment::create([
            'transcript_id' => $transcript->id,
            'start_time' => $first['start_time'],
            'end_time' => $last['end_time'],
            'content' => $words->pluck('content')->join(' '),
            'words' => $words->toArray(),
        ]);

        return Collection::make([]);
    }
}
