<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailReminder extends Model
{
    protected $fillable = [
        'user_id',
        'google_event_id',
        'event_title',
        'event_start',
        'event_location',
        'remind_at',
        'sent_at',
    ];

    protected $casts = [
        'event_start' => 'datetime',
        'remind_at'   => 'datetime',
        'sent_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return is_null($this->sent_at);
    }
}
