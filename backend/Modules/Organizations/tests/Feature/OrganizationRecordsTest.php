<?php

namespace Modules\Organizations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Organizations\Database\Seeders\OrganizationsDatabaseSeeder;
use Modules\Organizations\Models\Bank;
use Modules\Organizations\Models\Hospital;
use Tests\TestCase;

class OrganizationRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_seeder_creates_two_banks_and_two_hospitals(): void
    {
        $this->seed();

        $this->assertDatabaseCount('banks', 2);
        $this->assertDatabaseCount('hospitals', 2);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('banks', [
            'public_id' => 'demo-bank-damascus',
            'active' => true,
            'contact_email' => 'bank-a@example.org',
        ]);
    }

    public function test_reseeding_preserves_existing_facility_changes(): void
    {
        $this->seed(OrganizationsDatabaseSeeder::class);
        $bank = Bank::query()->where('public_id', 'demo-bank-damascus')->firstOrFail();
        $bank->update(['active' => false]);

        $this->seed(OrganizationsDatabaseSeeder::class);

        $this->assertDatabaseCount('banks', 2);
        $this->assertDatabaseCount('hospitals', 2);
        $this->assertDatabaseHas('banks', [
            'public_id' => 'demo-bank-damascus',
            'active' => false,
        ]);
    }

    public function test_models_generate_url_safe_ids_and_cast_facility_fields(): void
    {
        $bank = Bank::factory()->create();
        $hospital = Hospital::factory()->inactive()->create();

        $this->assertMatchesRegularExpression('/^[a-z0-9-]{1,64}$/', $bank->public_id);
        $this->assertSame('public_id', $bank->getRouteKeyName());
        $this->assertTrue($bank->active);
        $this->assertSame('public_id', $hospital->getRouteKeyName());
        $this->assertFalse($hospital->active);
        $this->assertNull($hospital->latitude);
        $this->assertNull($hospital->longitude);
    }

    public function test_active_bank_without_contact_channel_is_rejected(): void
    {
        try {
            Bank::factory()->create(['contact_email' => null, 'contact_phone' => null]);
            $this->fail('The bank should require a contact channel.');
        } catch (ValidationException $exception) {
            $this->assertSame('The contact email field is required.', $exception->errors()['contact_email'][0]);
        }

        $this->assertDatabaseCount('banks', 0);
    }

    public function test_active_hospital_without_coordinates_is_rejected(): void
    {
        try {
            Hospital::factory()->create(['latitude' => null]);
            $this->fail('The hospital should require coordinates.');
        } catch (ValidationException $exception) {
            $this->assertSame('The latitude field is required.', $exception->errors()['latitude'][0]);
        }

        $this->assertDatabaseCount('hospitals', 0);
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        try {
            Bank::factory()->create(['longitude' => 181]);
            $this->fail('The bank should reject invalid longitude.');
        } catch (ValidationException $exception) {
            $this->assertSame('The longitude field must be between -180 and 180.', $exception->errors()['longitude'][0]);
        }

        $this->assertDatabaseCount('banks', 0);
    }
}
