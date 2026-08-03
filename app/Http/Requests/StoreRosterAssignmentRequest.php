<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRosterAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('roster_period')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['doctor_identifier' => ['required', 'string', 'exists:doctors,identifier'], 'date' => ['required', 'date_format:Y-m-d'], 'duty_code' => ['nullable', 'in:standard_day'], 'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i'], 'credited_hours' => ['nullable', 'numeric', 'gt:0', 'max:24'], 'notes' => ['nullable', 'string', 'max:2000'], 'reason' => ['nullable', 'string', 'max:500']];
    }
}
