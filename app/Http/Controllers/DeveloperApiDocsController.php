<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\CurrentOrganization;
use Inertia\Inertia;
use Inertia\Response;

class DeveloperApiDocsController extends Controller
{
    public function __invoke(CurrentOrganization $currentOrganization): Response
    {
        $organization = $this->resolveOrganization($currentOrganization);

        return Inertia::render('Developers/ApiDocs', [
            'docsOrganization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'baseUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    private function resolveOrganization(CurrentOrganization $currentOrganization): Organization
    {
        $organization = $currentOrganization->organization;

        abort_if($organization === null, 404);

        return $organization;
    }
}
