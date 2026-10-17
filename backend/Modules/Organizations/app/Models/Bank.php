<?php

namespace Modules\Organizations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Organizations\Database\Factories\BankFactory;

#[Fillable(['display_name', 'address', 'contact_phone', 'contact_email', 'latitude', 'longitude', 'active'])]
class Bank extends Facility
{
    /** @use HasFactory<BankFactory> */
    use HasFactory;

    protected static function newFactory(): BankFactory
    {
        return BankFactory::new();
    }

    protected function validateAttributes(): void
    {
        parent::validateAttributes();

        Validator::make($this->getAttributes(), [
            'contact_email' => [Rule::requiredIf($this->active && blank($this->contact_phone)), 'nullable', 'email', 'max:254'],
        ])->validate();
    }
}
