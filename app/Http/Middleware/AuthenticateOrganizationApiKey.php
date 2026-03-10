<?php

namespace App\Http\Middleware;

use App\Services\ApiKeyService;
use App\Support\CurrentApiOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOrganizationApiKey
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || trim($token) === '') {
            return $this->unauthorized('Missing API key.');
        }

        $apiKey = $this->apiKeyService->findActiveByToken($token);

        if ($apiKey === null || $apiKey->organization === null) {
            return $this->unauthorized('Invalid API key.');
        }

        $organization = $apiKey->organization;

        if ($organization->is_suspended) {
            return response()->json([
                'success' => false,
                'message' => 'Organization is suspended.',
            ], 403);
        }

        $this->apiKeyService->touchLastUsed($apiKey);

        $currentApiOrganization = new CurrentApiOrganization($organization, $apiKey);

        app()->instance(CurrentApiOrganization::class, $currentApiOrganization);
        app()->instance('CurrentApiOrganization', $currentApiOrganization);

        $request->attributes->set('currentApiOrganization', $currentApiOrganization);
        $request->attributes->set('apiOrganizationId', $organization->id);
        $request->attributes->set('apiKeyId', $apiKey->id);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 401);
    }
}
