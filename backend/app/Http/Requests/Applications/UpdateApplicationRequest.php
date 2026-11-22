<?php

namespace App\Http\Requests\Applications;

use App\Models\SecurityAuditEvent;
use App\Models\User;
use App\Services\SecurityAuditService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRequest extends StoreApplicationRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return parent::authorize()
            && $application !== null
            && $application->student_id === $this->user()->getKey()
            && $application->isEditableByStudent();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'expected_version' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}