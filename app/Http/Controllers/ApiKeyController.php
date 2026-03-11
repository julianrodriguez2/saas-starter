<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApiKeyRequest;
use App\Models\ApiKey;
use App\Models\Organization;
use App\Services\ApiKeyService;
use App\Services\AuditLogger;
use App\Support\AuditActions;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ApiKeyController extends Controller
{
    public function index(CurrentOrganization $currentOrganization): Response
    {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('update', $organization);

        $keys = $organization->apiKeys()
            ->latest('created_at')
            ->get()
            ->map(fn (ApiKey $apiKey): array => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key_prefix' => $apiKey->key_prefix,
                'created_at' => $apiKey->created_at?->toIso8601String(),
                'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
                'revoked_at' => $apiKey->revoked_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('Settings/ApiKeys', [
            'apiKeysOrganization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'activeKeys' => collect($keys)
                ->filter(fn (array $key): bool => $key['revoked_at'] === null)
                ->values()
                ->all(),
            'revokedKeys' => collect($keys)
                ->filter(fn (array $key): bool => $key['revoked_at'] !== null)
                ->values()
                ->all(),
        ]);
    }

    public function store(
        StoreApiKeyRequest $request,
        CurrentOrganization $currentOrganization,
        ApiKeyService $apiKeyService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('update', $organization);

        try {
            $created = $apiKeyService->createKey(
                organization: $organization,
                name: $request->validated('name'),
                createdByUserId: $request->user()?->id
            );
        } catch (RuntimeException $exception) {
            report($exception);

            $message = 'Unable to create API key right now.';

            return redirect()->route('settings.api-keys.index')
                ->withErrors([
                    'name' => $message,
                ])
                ->with('error', $message);
        }

        /** @var ApiKey $apiKey */
        $apiKey = $created['apiKey'];
        $plainTextKey = $created['plainTextKey'];

        $auditLogger->logForOrganization(
            action: AuditActions::API_KEY_CREATED,
            organization: $organization,
            actor: $request->user(),
            targetType: 'api_key',
            targetId: (string) $apiKey->id,
            metadata: [
                'api_key_id' => $apiKey->id,
                'name' => $apiKey->name,
                'key_prefix' => $apiKey->key_prefix,
            ],
            request: $request
        );

        return redirect()->route('settings.api-keys.index')
            ->with('success', 'API key created. Save it now, it will not be shown again.')
            ->with('api_key_plaintext', $plainTextKey);
    }

    public function revoke(
        Request $request,
        CurrentOrganization $currentOrganization,
        ApiKey $apiKey,
        ApiKeyService $apiKeyService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('update', $organization);

        abort_if($apiKey->organization_id !== $organization->id, 404);

        $revoked = $apiKeyService->revoke($apiKey);

        if ($revoked) {
            $auditLogger->logForOrganization(
                action: AuditActions::API_KEY_REVOKED,
                organization: $organization,
                actor: $request->user(),
                targetType: 'api_key',
                targetId: (string) $apiKey->id,
                metadata: [
                    'api_key_id' => $apiKey->id,
                    'name' => $apiKey->name,
                    'key_prefix' => $apiKey->key_prefix,
                ],
                request: $request
            );

            return redirect()->route('settings.api-keys.index')
                ->with('success', 'API key revoked.');
        }

        return redirect()->route('settings.api-keys.index')
            ->with('warning', 'API key was already revoked.');
    }

    private function resolveOrganization(CurrentOrganization $currentOrganization): Organization
    {
        $organization = $currentOrganization->organization;

        abort_if($organization === null, 404);

        return $organization;
    }
}
