<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentrexCampaignResult extends Model
{
    protected $fillable = [
        'campaign_id', 'ip', 'status', 'code', 'reboot_required',
        'message', 'diagnostic', 'pbx_server_id', 'matching_state',
    ];

    protected function casts(): array
    {
        return [
            'code' => 'integer',
            'reboot_required' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CentrexCampaign::class, 'campaign_id');
    }

    public function pbxServer(): BelongsTo
    {
        return $this->belongsTo(PbxServer::class, 'pbx_server_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(CentrexCampaignStep::class, 'campaign_result_id');
    }
}
