<?php

namespace App\Http\Requests\Crypto;

use App\Services\Crypto\EnvelopeCryptoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyCryptoSimulationRequest extends FormRequest
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
            'mode' => ['required', 'string', Rule::in(EnvelopeCryptoService::SIMULATION_MODES)],
            'reference_code' => ['nullable', 'string', 'max:32'],
            'outcome' => ['nullable', 'string', Rule::in(EnvelopeCryptoService::CALLBACK_OUTCOMES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.in' => 'The simulation mode must be one of: valid callback, tampered ciphertext, forged signature, replay attack or expired timestamp.',
            'outcome.in' => 'The simulated outcome must be interview_passed or interview_failed.',
        ];
    }
}