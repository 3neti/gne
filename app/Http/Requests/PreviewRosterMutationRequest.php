<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewRosterMutationRequest extends FormRequest
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
        return ['operation' => ['required', 'in:add,remove,move,replace'], 'assignment_identifier' => ['required_if:operation,remove,move,replace', 'nullable', 'string', 'exists:roster_assignments,identifier'], 'doctor_identifier' => ['required_if:operation,add', 'nullable', 'string', 'exists:doctors,identifier'], 'replacement_doctor_identifier' => ['required_if:operation,replace', 'nullable', 'string', 'exists:doctors,identifier'], 'date' => ['required_if:operation,add', 'nullable', 'date_format:Y-m-d'], 'target_date' => ['required_if:operation,move', 'nullable', 'date_format:Y-m-d'], 'credited_hours' => ['nullable', 'numeric', 'gt:0', 'max:24'], 'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i']];
    }
}
