<?php

namespace App\Http\Requests;

use App\Models\RosterPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRosterPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RosterPeriod::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:100', 'regex:/^ROSTER-[A-Z0-9-]+$/', Rule::unique('roster_periods')],
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'default_weekday_requirement' => ['required', 'integer', 'min:0'],
            'default_weekend_requirement' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
