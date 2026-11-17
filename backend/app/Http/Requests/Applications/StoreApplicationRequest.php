<?php

namespace App\Http\Requests\Applications;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole(User::ROLE_STUDENT);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'host_company_name' => ['required', 'string', 'max:255'],
            'target_role' => ['required', 'string', 'max:255'],
            'internship_hours_required' => ['required', 'integer', 'min:1', 'max:5000'],
            'target_start_date' => ['required', 'date'],
            'requirements_checklist' => ['required', 'array'],
            'requirements_checklist.resume_ready' => ['required', 'boolean'],
            'requirements_checklist.endorsement_letter' => ['required', 'boolean'],
            'requirements_checklist.parent_consent' => ['required', 'boolean'],
            'requirements_checklist.medical_clearance' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'host_company_name' => 'host company',
            'target_role' => 'target role',
            'internship_hours_required' => 'required internship hours',
            'target_start_date' => 'target start date',
            'requirements_checklist.resume_ready' => 'resume readiness',
            'requirements_checklist.endorsement_letter' => 'endorsement letter',
            'requirements_checklist.parent_consent' => 'parent consent',
            'requirements_checklist.medical_clearance' => 'medical clearance',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'internship_hours_required.integer' => 'The required internship hours must be a whole number.',
            'internship_hours_required.min' => 'The required internship hours must be at least 1.',
            'target_start_date.date' => 'The target start date must be a valid calendar date.',
        ];
    }
}