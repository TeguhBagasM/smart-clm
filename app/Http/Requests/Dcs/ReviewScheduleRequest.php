<?php

namespace App\Http\Requests\Dcs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reviewDueAt' => ['nullable', 'date'],
            'reviewReminderDays' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reviewDueAt.date' => 'Tanggal tinjau ulang tidak valid.'];
    }

    public function dueAt(): ?CarbonImmutable
    {
        return $this->filled('reviewDueAt') ? CarbonImmutable::parse($this->string('reviewDueAt')->toString()) : null;
    }

    /**
     * How many days ahead to start reminding; anything that is not a positive
     * number falls back to 30, as in the Node API.
     */
    public function reminderDays(): int
    {
        $days = (int) $this->input('reviewReminderDays');

        return $days > 0 ? $days : 30;
    }
}
