<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'actor_id' => User::factory(),
            'actor_type' => 'user',
            'action' => 'system.event',
            'target_type' => 'organization',
            'target_id' => null,
            'metadata' => ['source' => 'factory'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ];
    }

    public function platformEvent(): static
    {
        return $this->state(fn (): array => [
            'organization_id' => null,
        ]);
    }
}
