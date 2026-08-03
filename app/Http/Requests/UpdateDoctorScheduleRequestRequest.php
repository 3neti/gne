<?php

namespace App\Http\Requests;

use App\Models\DoctorScheduleRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDoctorScheduleRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $request = $this->route('doctor_schedule_request');

        return $request instanceof DoctorScheduleRequest && ($this->user()?->can('update', $request) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:4000']];
    }
}
