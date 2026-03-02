<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BackgroundRemoverController extends Controller
{
    public function index()
    {
        return view('tools.background-remover');
    }

    public function remove(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $response = Http::withHeaders([
            'X-Api-Key' => env('REMOVEBG_API_KEY'),
        ])->attach(
            'image_file',
            file_get_contents($request->file('image')->getRealPath()),
            $request->file('image')->getClientOriginalName()
        )->post('https://api.remove.bg/v1.0/removebg', [
            'size' => 'auto',
        ]);

        if ($response->failed()) {
            return back()->with('error', 'Erreur lors du traitement de l\'image.');
        }

        return response($response->body(), 200)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="sans-fond.png"');
    }
}