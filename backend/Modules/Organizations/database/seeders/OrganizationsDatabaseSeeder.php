<?php

namespace Modules\Organizations\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Modules\Organizations\Models\Bank;
use Modules\Organizations\Models\Hospital;

class OrganizationsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Stable public IDs let repeated demonstration seeding preserve later staff edits.
        foreach ([
            [
                'public_id' => 'demo-bank-damascus',
                'display_name' => 'Demonstration Bank A',
                'address' => 'Demonstration address, Damascus',
                'contact_email' => 'bank-a@example.org',
                'latitude' => 33.513800,
                'longitude' => 36.276500,
            ],
            [
                'public_id' => 'demo-bank-aleppo',
                'display_name' => 'Demonstration Bank B',
                'address' => 'Demonstration address, Aleppo',
                'contact_email' => 'bank-b@example.org',
                'latitude' => 36.202100,
                'longitude' => 37.161200,
            ],
        ] as $attributes) {
            if (Bank::query()->where('public_id', $attributes['public_id'])->exists()) {
                continue;
            }

            $bank = new Bank(Arr::except($attributes, 'public_id'));
            $bank->public_id = $attributes['public_id'];
            $bank->save();
        }

        foreach ([
            [
                'public_id' => 'demo-hospital-damascus',
                'display_name' => 'Demonstration Hospital A',
                'address' => 'Demonstration address, Damascus',
                'contact_email' => 'hospital-a@example.org',
                'latitude' => 33.505000,
                'longitude' => 36.285000,
            ],
            [
                'public_id' => 'demo-hospital-aleppo',
                'display_name' => 'Demonstration Hospital B',
                'address' => 'Demonstration address, Aleppo',
                'contact_email' => 'hospital-b@example.org',
                'latitude' => 36.210000,
                'longitude' => 37.155000,
            ],
        ] as $attributes) {
            if (Hospital::query()->where('public_id', $attributes['public_id'])->exists()) {
                continue;
            }

            $hospital = new Hospital(Arr::except($attributes, 'public_id'));
            $hospital->public_id = $attributes['public_id'];
            $hospital->save();
        }
    }
}
