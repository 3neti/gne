<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRosterStaffingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('roster_period')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['weekday_default' => ['nullable', 'integer', 'min:0'], 'weekend_default' => ['nullable', 'integer', 'min:0'], 'requirements' => ['array'], 'requirements.*' => ['nullable', 'integer', 'min:0']];
    }
}
