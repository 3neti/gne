<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDoctorRosterRequirementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('roster_period')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['requirements' => ['required', 'array'], 'requirements.*.required_hours' => ['nullable', 'numeric', 'min:0'], 'requirements.*.notes' => ['nullable', 'string']];
    }
}
