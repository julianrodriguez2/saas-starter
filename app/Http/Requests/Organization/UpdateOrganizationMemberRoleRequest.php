<?php

namespace App\Http\Requests\Organization;

use App\Models\OrganizationUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in([OrganizationUser::ROLE_ADMIN, OrganizationUser::ROLE_MEMBER])],
        ];
    }
}
