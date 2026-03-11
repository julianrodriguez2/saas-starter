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
     * @return array{id: string, name: string, is_suspended: bool, can_write: bool}|null
     */
    public function toArray(): ?array
    {
        if ($this->organization === null) {
            return null;
        }

        return [
            'id' => $this->organization->id,
            'name' => $this->organization->name,
            'is_suspended' => (bool) $this->organization->is_suspended,
            'can_write' => $this->organization->canPerformWrites(),
        ];
    }
}
