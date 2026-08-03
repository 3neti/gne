<?php

namespace App\Http\Requests;

use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\DoctorRequestType;
use App\Models\DoctorScheduleRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDoctorScheduleRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('dates_text')) || trim($this->string('dates_text')->toString()) === '') {
            return;
        }

        $dates = preg_split('/[\s,]+/', trim($this->string('dates_text')->toString()), -1, PREG_SPLIT_NO_EMPTY);
        $this->merge(['dates' => $dates === false ? [] : $dates]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', DoctorScheduleRequest::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['doctor' => ['required', 'string', Rule::exists('doctors', 'identifier')], 'roster_period' => ['required', 'string', Rule::exists('roster_periods', 'identifier')], 'request_type' => ['required', Rule::enum(DoctorRequestType::class)], 'status' => ['required', Rule::in([DoctorRequestStatus::Submitted->value, DoctorRequestStatus::Accepted->value])], 'starts_on' => ['nullable', 'date_format:Y-m-d', 'required_without:dates'], 'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'required_with:starts_on'], 'dates_text' => ['nullable', 'string'], 'dates' => ['nullable', 'array', 'min:1', 'required_without:starts_on'], 'dates.*' => ['required', 'date_format:Y-m-d', 'distinct'], 'reason' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:4000']];
    }
}
