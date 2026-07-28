<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruckingCompanyDriver extends Model
{
    protected $fillable = [
        'trucking_company_id', 'name', 'phone',
    ];

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = \App\Support\PhoneNumberFormatter::format($value);
    }

    public function truckingCompany(): BelongsTo
    {
        return $this->belongsTo(TruckingCompany::class);
    }
}
