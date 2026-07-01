<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;

class QrCodeController extends Controller
{
    public function index()
    {
        return view('tools.qr-code');
    }
}
