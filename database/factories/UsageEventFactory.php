<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageEvent>
 */
class UsageEventFactory extends Factory
{
    protected $model = UsageEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_type' => 'api_call',
            'quantity' => $this->faker->numberBetween(1, 5),
            'metadata' => [
                'source' => 'factory',
            ],
            'recorded_at' => now(),
        ];
    }
}
