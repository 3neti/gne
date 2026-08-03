<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRosterPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('roster_period')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string']];
    }
}
