<?php

namespace OnrampLab\Transcription\Redactors;

use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Wav;
use GuzzleHttp\Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use OnrampLab\Transcription\Contracts\AudioRedactor as AudioRedactorContract;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\ValueObjects\EntityAudio;

class AudioRedactor implements AudioRedactorContract
{
    /**
     * Redact the audio file of transcript for PII entities.
     *
     * @param Collection<EntityAudio> $entityAudios
     */
    public function redact(Transcript $transcript, Collection $entityAudios, Filesystem $audioDisk, string $audioFolder): void
    {
        $tempFileName = $this->downloadAudioFile($transcript->audio_file_url);
        $audioFileName = $this->redactAudioFile($entityAudios, $tempFileName, $audioDisk, $audioFolder);

        $this->updateAudioUrl($transcript, $audioFileName, $audioDisk);
    }

    private function downloadAudioFile(string $fileUrl): string
    {
        $tempFileName = Str::uuid()->toString();
        $tempFilePath = Storage::disk('local')->path($tempFileName);
        /** @var Client $httpClient */
        $httpClient = App::make(Client::class);
        $httpClient->request('GET', $fileUrl, ['sink' => $tempFilePath]);

        return $tempFileName;
    }

    private function redactAudioFile(Collection $entityAudios, string $tempFileName, Filesystem $audioDisk, string $audioFolder): string
    {
        $localDisk = Storage::disk('local');
        $outputFileName = sprintf('%s.wav', Str::uuid()->toString());
        $audioFileName = sprintf('%s%s.wav', $audioFolder ? $audioFolder . '/' : '', Str::uuid()->toString());

        $parameters = $entityAudios->map(function (EntityAudio $entityAudio) {
            $from = Carbon::parse($entityAudio->startTime)->diffInMilliseconds(now()->startOfDay()) / 1000;
            $to = Carbon::parse($entityAudio->endTime)->diffInMilliseconds(now()->startOfDay()) / 1000;

            return "volume=enable='between(t,{$from},{$to})':volume=0";
        });
        $ffmpeg = FFMpeg::create();
        $audio = $ffmpeg->open($localDisk->path($tempFileName));
        $audio->filters()->custom($parameters->join(','));
        $audio->save(new Wav(), $localDisk->path($outputFileName));

        $audioDisk->put($audioFileName, $localDisk->get($outputFileName));
        $localDisk->delete($tempFileName);
        $localDisk->delete($outputFileName);

        return $audioFileName;
    }

    private function updateAudioUrl(Transcript $transcript, string $audioFileName, Filesystem $audioDisk): void
    {
        $transcript->audio_file_url_redacted = $audioDisk->url($audioFileName);
        $transcript->save();
    }
}
