<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentExtractorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,gif,webp,txt,csv,json',
        ]);

        try {
            $extractor = new DocumentExtractorService();
            $result    = $extractor->extract($request->file('file'));

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
