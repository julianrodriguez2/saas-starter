<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'stripe_price_id' => 'price_free_placeholder',
                'limits' => [
                    'team_members' => 3,
                    'api_calls_per_month' => 1000,
                    'projects' => 2,
                    'advanced_features' => false,
                ],
            ],
            [
                'name' => 'Pro',
                'stripe_price_id' => 'price_pro_placeholder',
                'limits' => [
                    'team_members' => 10,
                    'api_calls_per_month' => 50000,
                    'projects' => 20,
                    'advanced_features' => true,
                ],
            ],
            [
                'name' => 'Enterprise',
                'stripe_price_id' => 'price_enterprise_placeholder',
                'limits' => [
                    'team_members' => null,
                    'api_calls_per_month' => null,
                    'projects' => null,
                    'advanced_features' => true,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
