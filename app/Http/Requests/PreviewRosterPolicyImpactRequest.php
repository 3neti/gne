<?php

namespace App\Http\Requests;

use App\Domain\Rostering\RosterPolicyDefinition;
use App\Models\RosterPolicyCalibration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewRosterPolicyImpactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', RosterPolicyCalibration::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $policyKey = (string) $this->input('policy_key');
        $knownKeys = ['unspecified_availability', 'required_hours_meaning', 'structural_hours_allocation', 'weekend_distribution', 'consecutive_day_limit', 'target_hours_cap', 'employment_type_eligibility', 'preference_strength'];
        $allowedValues = in_array($policyKey, $knownKeys, true) ? RosterPolicyDefinition::allowedValues($policyKey) : [];

        return ['policy_key' => ['required', Rule::in($knownKeys)], 'candidate_value' => ['required', 'string', Rule::in($allowedValues)]];
    }
}
