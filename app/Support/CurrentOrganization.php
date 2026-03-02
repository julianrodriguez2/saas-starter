<?php

namespace App\Support;

use App\Models\Organization;

class CurrentOrganization
{
    public function __construct(
        public readonly ?Organization $organization = null
    ) {
    }

    /**
     * @return array{id: string, name: string}|null
     */
    public function toArray(): ?array
    {
        return $this->organization?->only(['id', 'name']);
    }
}
