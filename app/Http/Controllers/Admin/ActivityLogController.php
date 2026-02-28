<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with('user')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.logs.index', compact('logs'));
    }
}
