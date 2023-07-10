<?php

use Illuminate\Support\Facades\Route;
use OnrampLab\Transcription\Http\Controllers\TranscriptionController;

Route::post('transcription/{type}/callback', [TranscriptionController::class, 'callback'])->name('transcription.callback');
