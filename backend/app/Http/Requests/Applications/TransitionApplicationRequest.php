<?php

namespace App\Http\Requests\Applications;

use App\Models\InternshipApplication;
use App\Models\User;
use App\Support\ApplicationStatusMachine;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_status' => ['required', 'string', Rule::in(InternshipApplication::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'partner_reviewer_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'interview_schedule' => ['nullable', 'date'],
            'interview_notes' => ['nullable', 'string', 'max:2000'],
            'expected_version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $target = (string) $this->input('target_status');

                if (! in_array($target, InternshipApplication::STATUSES, true)) {
                    return;
                }

                if (ApplicationStatusMachine::requiresPartnerReviewer($target)) {
                    $this->validatePartnerReviewer($validator);
                }

                if (ApplicationStatusMachine::requiresInterviewSchedule($target)
                    && blank($this->input('interview_schedule'))) {
                    $validator->errors()->add(
                        'interview_schedule',
                        'An interview schedule is required when scheduling an interview.',
                    );
                }

                if (ApplicationStatusMachine::requiresRemarks($target)
                    && blank($this->input('remarks'))) {
                    $validator->errors()->add(
                        'remarks',
                        'Remarks are required when requesting a revision or rejecting an application.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'target_status' => 'target status',
            'partner_reviewer_id' => 'partner reviewer',
            'interview_schedule' => 'interview schedule',
        ];
    }

    private function validatePartnerReviewer(Validator $validator): void
    {
        $partnerReviewerId = $this->input('partner_reviewer_id');

        if (blank($partnerReviewerId)) {
            $validator->errors()->add(
                'partner_reviewer_id',
                'A partner reviewer must be selected before submitting an application to a partner.',
            );

            return;
        }

        $exists = User::query()
            ->whereKey($partnerReviewerId)
            ->where('role', User::ROLE_PARTNER_REVIEWER)
            ->exists();

        if (! $exists) {
            $validator->errors()->add(
                'partner_reviewer_id',
                'The selected partner reviewer is not an active partner reviewer account.',
            );
        }
    }
}