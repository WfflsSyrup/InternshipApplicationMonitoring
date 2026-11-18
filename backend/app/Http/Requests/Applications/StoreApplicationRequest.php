<?php

namespace App\Http\Requests\Applications;

use App\Models\SecurityAuditEvent;
use App\Models\User;
use App\Services\SecurityAuditService;
use Illuminate\Contracts\Validation\Validator;
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
        $rules = [
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

        foreach ($this->bannedAttributes() as $attribute) {
            $rules[$attribute] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * Week 15 — Module 15 / SA9 (BOPLA): privileged columns may never be
     * written by a client, not even by a coordinator through this endpoint.
     *
     * @return array<int, string>
     */
    private function bannedAttributes(): array
    {
        return array_values(array_filter(
            (array) config('security.banned_attributes', []),
            static fn (mixed $attribute): bool => is_string($attribute) && $attribute !== '',
        ));
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
        $messages = [
            'internship_hours_required.integer' => 'The required internship hours must be a whole number.',
            'internship_hours_required.min' => 'The required internship hours must be at least 1.',
            'target_start_date.date' => 'The target start date must be a valid calendar date.',
        ];

        foreach ($this->bannedAttributes() as $attribute) {
            $messages[$attribute.'.prohibited'] = 'The '.$attribute.' field is managed by the internship office and cannot be set by the client.';
        }

        return $messages;
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->auditBlockedAttributes();

        parent::failedValidation($validator);
    }

    private function auditBlockedAttributes(): void
    {
        $banned = $this->bannedAttributes();

        if ($banned === []) {
            return;
        }

        $present = array_values(array_filter(
            $banned,
            fn (string $attribute): bool => $this->has($attribute),
        ));

        if ($present === []) {
            return;
        }

        try {
            app(SecurityAuditService::class)->record(
                SecurityAuditEvent::EVENT_MASS_ASSIGNMENT_BLOCKED,
                422,
                [
                    'attributes' => $present,
                    'endpoint_method' => $this->getMethod(),
                ],
                $this->user(),
                $this,
            );
        } catch (\Throwable) {
            // Validation must still fail cleanly when telemetry is unavailable.
        }
    }
}