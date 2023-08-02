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
        $fileName = Str::uuid()->toString();
        $fileName = $audioFolder ? $audioFolder . '/' . $fileName : $fileName;
        $localDisk = Storage::disk('local');

        $this->downloadAudioFile($transcript->audio_file_url, $fileName, $localDisk);
        $this->redactAudioFile($entityAudios, $fileName, $localDisk, $audioDisk);
        $this->updateAudioUrl($transcript, $fileName, $audioDisk);
    }

    private function downloadAudioFile(string $audioUrl, string $fileName, Filesystem $localDisk): void
    {
        $filePath = $localDisk->path($fileName);
        /** @var Client $httpClient */
        $httpClient = App::make(Client::class);
        $httpClient->request('GET', $audioUrl, ['sink' => $filePath]);
    }

    private function redactAudioFile(Collection $entityAudios, string $fileName, Filesystem $localDisk, Filesystem $audioDisk): void
    {
        $filePath = $localDisk->path($fileName);
        $parameters = $entityAudios->map(function (EntityAudio $entityAudio) {
            $from = Carbon::parse($entityAudio->startTime)->diffInMilliseconds(now()->startOfDay()) / 1000;
            $to = Carbon::parse($entityAudio->endTime)->diffInMilliseconds(now()->startOfDay()) / 1000;

            return "volume=enable='between(t,{$from},{$to})':volume=0";
        });
        $ffmpeg = FFMpeg::create();
        $audio = $ffmpeg->open($filePath);
        $audio->filters()->custom($parameters->join(','));
        $audio->save(new Wav(), $filePath);

        $audioDisk->put($fileName, $localDisk->get($fileName));
        $localDisk->delete($fileName);
    }

    private function updateAudioUrl(Transcript $transcript, string $fileName, Filesystem $audioDisk): void
    {
        $transcript->audio_file_url_redacted = $audioDisk->url($fileName);
        $transcript->save();
    }
}
