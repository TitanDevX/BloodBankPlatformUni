<?php

namespace Modules\Organizations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organizations\Models\Hospital;

/** @extends Factory<Hospital> */
class HospitalFactory extends Factory
{
    protected $model = Hospital::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'display_name' => fake()->company(),
            'address' => fake()->address(),
            'contact_phone' => null,
            'contact_email' => fake()->safeEmail(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'latitude' => null,
            'longitude' => null,
            'active' => false,
        ]);
    }
}
