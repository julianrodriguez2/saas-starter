<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Plan;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed realistic demo data for local development.
     */
    public function run(): void
    {
        $freePlan = Plan::query()->whereRaw('lower(name) = ?', ['free'])->first();
        $proPlan = Plan::query()->whereRaw('lower(name) = ?', ['pro'])->first();

        $owner = User::query()->firstOrCreate(
            ['email' => 'owner@example.com'],
            [
                'name' => 'Owner User',
                'password' => Hash::make('password'),
            ]
        );

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );

        $member = User::query()->firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Member User',
                'password' => Hash::make('password'),
            ]
        );

        $organization = Organization::query()->firstOrCreate(
            ['name' => 'Acme Demo Organization'],
            [
                'owner_id' => $owner->id,
                'plan_id' => $proPlan?->id ?? $freePlan?->id,
            ]
        );

        $organization->users()->syncWithoutDetaching([
            $owner->id => ['role' => OrganizationUser::ROLE_OWNER],
            $admin->id => ['role' => OrganizationUser::ROLE_ADMIN],
            $member->id => ['role' => OrganizationUser::ROLE_MEMBER],
        ]);

        if (UsageEvent::query()->where('organization_id', $organization->id)->exists()) {
            return;
        }

        for ($index = 0; $index < 25; $index++) {
            UsageEvent::query()->create([
                'organization_id' => $organization->id,
                'event_type' => 'api_call',
                'quantity' => random_int(1, 5),
                'metadata' => [
                    'source' => 'seeder.demo',
                ],
                'recorded_at' => now()->startOfMonth()->addDays(random_int(0, 27)),
            ]);
        }
    }
}
