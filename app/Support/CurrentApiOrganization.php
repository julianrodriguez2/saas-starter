<?php

namespace App\Support;

use App\Models\ApiKey;
use App\Models\Organization;

class CurrentApiOrganization
{
    public function __construct(
        public readonly Organization $organization,
        public readonly ApiKey $apiKey
    ) {
    }

    /**
     * @return array{id: string, name: string, api_key_id: int, api_key_prefix: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->organization->id,
            'name' => $this->organization->name,
            'api_key_id' => $this->apiKey->id,
            'api_key_prefix' => $this->apiKey->key_prefix,
        ];
    }
}
