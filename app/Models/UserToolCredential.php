<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserToolCredential extends Model
{
    protected $fillable = ['user_id', 'tool_id', 'login', 'password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted', // AES-256 via APP_KEY
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tool()
    {
        return $this->belongsTo(Tool::class);
    }
}
