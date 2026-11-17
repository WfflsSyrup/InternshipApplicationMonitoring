<?php

namespace App\Http\Requests\Crypto;

use App\Models\InternshipApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispatchPartnerPacketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'application_id' => ['required', 'integer', Rule::exists('internship_applications', 'id')],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'include_interview' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'application_id.exists' => 'The selected internship application no longer exists.',
        ];
    }

    public function application(): InternshipApplication
    {
        /** @var InternshipApplication $application */
        $application = InternshipApplication::query()
            ->findOrFail((int) $this->validated('application_id'));

        return $application;
    }
}