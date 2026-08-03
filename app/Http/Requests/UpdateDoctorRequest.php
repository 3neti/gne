<?php

namespace App\Http\Requests;

use App\Domain\Rostering\ContractedHoursPeriod;
use App\Domain\Rostering\EmploymentType;
use App\Models\Doctor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('doctor')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Doctor $doctor */
        $doctor = $this->route('doctor');

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'employee_identifier' => ['nullable', 'string', 'max:100', Rule::unique('doctors')->ignore($doctor)],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'contracted_hours' => ['nullable', 'numeric', 'min:0', 'required_with:contracted_hours_period'],
            'contracted_hours_period' => ['nullable', Rule::enum(ContractedHoursPeriod::class), 'required_with:contracted_hours'],
            'standard_daily_hours' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
