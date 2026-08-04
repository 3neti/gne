<?php

namespace App\Http\Requests;

use App\Models\RosterPolicyCalibration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmRosterPolicyCalibrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('confirmPolicyCalibration', RosterPolicyCalibration::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'policy_key' => ['required', Rule::in(['unspecified_availability', 'required_hours_meaning', 'structural_hours_allocation', 'weekend_distribution', 'consecutive_day_limit', 'target_hours_cap', 'employment_type_eligibility', 'preference_strength'])],
            'selected_value' => ['required', 'string', 'max:100'],
            'effective_from' => ['nullable', 'date'],
            'source_reference' => ['required', 'string', 'max:255'],
            'notes' => ['required', 'string', 'max:2000'],
        ];
    }
}
