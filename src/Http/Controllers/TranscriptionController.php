<?php

namespace OnrampLab\Transcription\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OnrampLab\Transcription\Facades\Transcription;

class TranscriptionController extends Controller
{
    /**
     * Execute asynchronous transcription callback
     */
    public function callback(Request $request, string $type): JsonResponse
    {
        Transcription::callback($type, $request->headers->all(), $request->input());

        return response()->json([], Response::HTTP_OK);
    }
}
