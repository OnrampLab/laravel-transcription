<?php

namespace OnrampLab\Transcription\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use OnrampLab\Transcription\Events\TranscriptCompletedEvent;
use OnrampLab\Transcription\Listeners\RedactTranscriptListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        TranscriptCompletedEvent::class => [
            RedactTranscriptListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
