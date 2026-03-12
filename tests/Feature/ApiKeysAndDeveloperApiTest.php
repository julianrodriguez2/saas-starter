<?php

namespace Tests\Feature;

use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\InteractsWithTenantTestData;
use Tests\TestCase;

class ApiKeysAndDeveloperApiTest extends TestCase
{
    use InteractsWithTenantTestData;
    use RefreshDatabase;

    public function test_owner_can_create_api_key(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan());

        $response = $this->actingAsInOrganization($owner, $organization)
            ->post('/settings/api-keys', [
                'name' => 'Owner Key',
            ]);

        $response->assertRedirect('/settings/api-keys')
            ->assertSessionHas('api_key_plaintext');

        $this->assertDatabaseHas('api_keys', [
            'organization_id' => $organization->id,
            'name' => 'Owner Key',
        ]);

    }

    public function test_admin_can_create_api_key(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan());
        $this->addOrganizationMember($organization, $admin, OrganizationUser::ROLE_ADMIN);

        $this->actingAsInOrganization($admin, $organization)
            ->post('/settings/api-keys', [
                'name' => 'Admin Key',
            ])
            ->assertRedirect('/settings/api-keys');

        $this->assertDatabaseHas('api_keys', [
            'organization_id' => $organization->id,
            'name' => 'Admin Key',
        ]);
    }

    public function test_plaintext_api_key_is_available_only_at_creation_time_service_level(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $service = app(ApiKeyService::class);

        $created = $service->createKey($organization, 'Backend Key');
        $plainTextKey = $created['plainTextKey'];
        $apiKey = $created['apiKey'];

        $this->assertNotSame($plainTextKey, $apiKey->key_hash);
        $this->assertSame(hash('sha256', $plainTextKey), $apiKey->key_hash);
        $this->assertNotNull($service->findActiveByToken($plainTextKey));
    }

    public function test_revoked_api_key_cannot_authenticate(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $service = app(ApiKeyService::class);
        $created = $service->createKey($organization, 'Revoked Key');
        $plainTextKey = $created['plainTextKey'];
        $apiKey = $created['apiKey'];

        $service->revoke($apiKey);

        $this->withHeader('Authorization', "Bearer {$plainTextKey}")
            ->postJson('/api/v1/entitlements/check', [
                'feature' => 'team_members',
            ])
            ->assertUnauthorized();
    }

    public function test_valid_api_key_can_call_usage_events_endpoint(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $service = app(ApiKeyService::class);
        $plainTextKey = $service->createKey($organization, 'Usage Key')['plainTextKey'];

        $this->withHeader('Authorization', "Bearer {$plainTextKey}")
            ->postJson('/api/v1/usage-events', [
                'event_type' => 'api_call',
                'quantity' => 2,
                'metadata' => ['source' => 'phpunit'],
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('usage_events', [
            'organization_id' => $organization->id,
            'event_type' => 'api_call',
            'quantity' => 2,
        ]);
    }

    public function test_valid_api_key_can_call_entitlement_check_endpoint(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $service = app(ApiKeyService::class);
        $plainTextKey = $service->createKey($organization, 'Entitlement Key')['plainTextKey'];

        $this->withHeader('Authorization', "Bearer {$plainTextKey}")
            ->postJson('/api/v1/entitlements/check', [
                'feature' => 'advanced_features',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'feature' => 'advanced_features',
                'allowed' => false,
            ]);
    }

    public function test_api_rate_limiting_returns_429_after_threshold(): void
    {
        $this->seedPlans();
        config()->set('platform.rate_limits.organization_api_per_minute', 2);

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $service = app(ApiKeyService::class);
        $plainTextKey = $service->createKey($organization, 'Rate Limit Key')['plainTextKey'];

        RateLimiter::clear("org:{$organization->id}");

        $payload = ['feature' => 'team_members'];

        $this->withHeader('Authorization', "Bearer {$plainTextKey}")
            ->postJson('/api/v1/entitlements/check', $payload)
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$plainTextKey}")
            ->postJson('/api/v1/entitlements/check', $payload)
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$plainTextKey}")
            ->postJson('/api/v1/entitlements/check', $payload)
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }
}
