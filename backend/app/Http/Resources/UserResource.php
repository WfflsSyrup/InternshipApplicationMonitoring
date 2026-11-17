<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_or_employee_id' => $this->student_or_employee_id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_label' => ucwords(str_replace('_', ' ', (string) $this->role)),
            'department_or_company' => $this->department_or_company,
        ];
    }
}