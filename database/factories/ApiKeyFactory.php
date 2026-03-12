<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $identifier = bin2hex(random_bytes(4));
        $plainText = "sk_{$identifier}_".bin2hex(random_bytes(32));

        return [
            'organization_id' => Organization::factory(),
            'name' => 'Test Key '.$this->faker->word(),
            'key_prefix' => "sk_{$identifier}",
            'key_hash' => hash('sha256', $plainText),
            'last_used_at' => null,
            'revoked_at' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
        ]);
    }
}
