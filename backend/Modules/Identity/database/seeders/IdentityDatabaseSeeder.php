<?php

namespace Modules\Identity\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Identity\Models\Role;

class IdentityDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            [Role::Administrator, 'Administrator', Role::System],
            ['bank_admin', 'Bank administrator', Role::Bank],
            ['bank_donor_collection', 'Donor collection', Role::Bank],
            ['bank_inventory', 'Bank inventory', Role::Bank],
            ['bank_fulfillment', 'Bank fulfillment', Role::Bank],
            ['laboratory_testing', 'Laboratory testing', Role::Bank],
            ['laboratory_release', 'Laboratory release', Role::Bank],
            ['hospital_requester', 'Hospital requester', Role::Hospital],
        ] as [$code, $displayName, $scope]) {
            Role::query()->updateOrCreate(
                ['code' => $code],
                ['display_name' => $displayName, 'scope' => $scope],
            );
        }

        // Demo credentials are never provisioned outside local/test environments.
        if (! app()->environment(['local', 'testing']) || ! config('identity.demo_admin_enabled')) {
            return;
        }

        if (User::query()->where('email', 'admin@demo.com')->exists()) {
            return;
        }

        $admin = new User;
        $admin->name = 'Demo Administrator';
        $admin->email = 'admin@demo.com';
        $admin->password = config('identity.demo_admin_password');
        $admin->active = true;
        $admin->role()->associate(Role::query()->where('code', Role::Administrator)->firstOrFail());
        $admin->save();
    }
}
