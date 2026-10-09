<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentrexCampaignStep extends Model
{
    protected $fillable = ['component', 'status', 'version', 'observed_at'];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime'];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(CentrexCampaignResult::class, 'campaign_result_id');
    }
}
