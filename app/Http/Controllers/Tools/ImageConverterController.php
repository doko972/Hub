<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;

class ImageConverterController extends Controller
{
    public function index()
    {
        return view('tools.image-converter');
    }
}
