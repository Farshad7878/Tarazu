<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsCampaignRecipient extends Model
{
    protected $fillable = [
        'sms_campaign_id',
        'customer_id',
        'phone',
        'status',
        'error',
        'sent_at',
        'claimed_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(SmsCampaign::class, 'sms_campaign_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
