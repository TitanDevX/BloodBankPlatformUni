<?php

namespace Modules\Organizations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Organizations\Database\Factories\HospitalFactory;

#[Fillable(['display_name', 'address', 'contact_phone', 'contact_email', 'latitude', 'longitude', 'active'])]
class Hospital extends Facility
{
    /** @use HasFactory<HospitalFactory> */
    use HasFactory;

    protected static function newFactory(): HospitalFactory
    {
        return HospitalFactory::new();
    }
}
