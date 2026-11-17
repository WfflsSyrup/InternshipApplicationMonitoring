<?php

namespace App\Http\Requests\Automation;

use App\Services\Automation\AutomationSweepService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunAutomationSweepRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(AutomationSweepService::TYPES)],
            'fault_mode' => ['nullable', 'string', Rule::in($this->faultModes())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'The sweep type must be one of: incomplete, stalled, weekly_summary.',
            'fault_mode.in' => 'The fault mode is not a recognised controlled fault injection mode.',
        ];
    }

    public function faultMode(): ?string
    {
        $faultMode = $this->input('fault_mode');

        return is_string($faultMode) && $faultMode !== '' && $faultMode !== 'none'
            ? $faultMode
            : null;
    }

    /**
     * @return array<int, string>
     */
    private function faultModes(): array
    {
        $modes = (array) config('automation.fault_modes', []);

        return array_values(array_map('strval', $modes));
    }
}