<?php

namespace Modules\Organizations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

abstract class Facility extends Model
{
    protected $attributes = ['active' => true];

    protected static function booted(): void
    {
        static::saving(function (self $facility): void {
            $facility->validateAttributes();
        });

        static::creating(function (self $facility): void {
            $facility->public_id ??= strtolower((string) Str::ulid());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
    }

    protected function validateAttributes(): void
    {
        Validator::make($this->getAttributes(), [
            'display_name' => ['required', 'string', 'max:200'],
            'address' => ['required', 'string', 'max:500'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'latitude' => [Rule::requiredIf($this->active), 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [Rule::requiredIf($this->active), 'nullable', 'numeric', 'between:-180,180'],
            'active' => ['required', 'boolean'],
        ])->validate();
    }
}
