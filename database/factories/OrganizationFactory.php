<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Organization',
            'owner_id' => User::factory(),
            'plan_id' => null,
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
            'trial_ends_at' => null,
            'is_suspended' => false,
            'suspended_at' => null,
            'suspension_reason' => null,
        ];
    }

    public function suspended(string $reason = 'Policy violation'): static
    {
        return $this->state(fn (): array => [
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Organization $organization): void {
            if (! is_numeric($organization->owner_id)) {
                return;
            }

            $ownerId = (int) $organization->owner_id;

            $organization->users()->syncWithoutDetaching([
                $ownerId => ['role' => OrganizationUser::ROLE_OWNER],
            ]);
        });
    }
}
