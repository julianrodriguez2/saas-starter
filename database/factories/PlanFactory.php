<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Plan '.$this->faker->unique()->word(),
            'stripe_price_id' => 'price_'.$this->faker->unique()->bothify('????????'),
            'limits' => [
                'team_members' => 3,
                'api_calls_per_month' => 1000,
                'projects' => 2,
                'advanced_features' => false,
            ],
        ];
    }

    public function free(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Free',
            'stripe_price_id' => 'price_free_placeholder',
            'limits' => [
                'team_members' => 3,
                'api_calls_per_month' => 1000,
                'projects' => 2,
                'advanced_features' => false,
            ],
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Pro',
            'stripe_price_id' => 'price_pro_placeholder',
            'limits' => [
                'team_members' => 10,
                'api_calls_per_month' => 50000,
                'projects' => 20,
                'advanced_features' => true,
            ],
        ]);
    }

    public function enterprise(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Enterprise',
            'stripe_price_id' => 'price_enterprise_placeholder',
            'limits' => [
                'team_members' => null,
                'api_calls_per_month' => null,
                'projects' => null,
                'advanced_features' => true,
            ],
        ]);
    }
}
