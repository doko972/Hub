<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentrexCampaign extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'script_version', 'operation', 'operation_label', 'scope',
        'started_at', 'finished_at', 'targets_complete', 'processed',
        'succeeded', 'failed', 'reboot_flagged', 'reboot_coverage_complete',
        'payload_sha256',
        'component_versions',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'targets_complete' => 'boolean',
            'reboot_coverage_complete' => 'boolean',
            'processed' => 'integer',
            'succeeded' => 'integer',
            'failed' => 'integer',
            'reboot_flagged' => 'integer',
            'component_versions' => 'array',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(CentrexCampaignResult::class, 'campaign_id');
    }
}
