<?php

namespace OnrampLab\Transcription\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OnrampLab\Transcription\Contracts\TranscriptionManager;

class TranscriptionController extends Controller
{
    /**
     * Execute asynchronous transcription callback
     */
    public function callback(TranscriptionManager $manager, Request $request, string $type): JsonResponse
    {
        $manager->callback($type, $request->header(), $request->input());

        return response()->json([], Response::HTTP_OK);
    }
}
