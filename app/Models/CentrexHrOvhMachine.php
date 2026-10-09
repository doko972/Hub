<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentrexHrOvhMachine extends Model
{
    protected $table = 'centrex_hr_ovh_machines';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_present' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
