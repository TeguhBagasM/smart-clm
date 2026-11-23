<?php

namespace App\Http\Requests\Documents;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

class ReviewScheduleRequest extends DocumentRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review_due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'reminder_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'review_due_at.date' => 'Tanggal tinjau ulang tidak valid.',
            'review_due_at.after_or_equal' => 'Pilih tanggal hari ini atau sesudahnya.',
            'reminder_days.integer' => 'Isi jumlah hari dengan angka.',
            'reminder_days.min' => 'Pengingat paling cepat 1 hari sebelumnya.',
            'reminder_days.max' => 'Pengingat paling lama 365 hari sebelumnya.',
        ];
    }

    /**
     * None clears the schedule.
     */
    public function dueAt(): ?CarbonImmutable
    {
        return $this->filled('review_due_at') ? CarbonImmutable::parse($this->string('review_due_at')->toString()) : null;
    }

    public function reminderDays(): int
    {
        return $this->filled('reminder_days') ? $this->integer('reminder_days') : 30;
    }
}
