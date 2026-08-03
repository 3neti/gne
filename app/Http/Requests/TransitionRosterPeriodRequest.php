<?php

namespace App\Http\Requests;

use App\Domain\Rostering\RosterPeriodStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionRosterPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transition', $this->route('roster_period')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(RosterPeriodStatus::class)], 'reason' => ['nullable', 'string', 'max:1000']];
    }
}
