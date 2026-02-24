<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $tools = auth()->user()->visibleTools();
        return view('dashboard.index', compact('tools'));
    }
}
