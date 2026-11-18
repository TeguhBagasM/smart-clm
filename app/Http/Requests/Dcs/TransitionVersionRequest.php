<?php

namespace App\Http\Requests\Dcs;

use App\Enums\Dcs\VersionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The generic status change. Only the two moves a person makes by hand are
 * accepted: submit for review and pull back to draft. Approval comes from
 * signing and release from Document Control, never from this endpoint.
 */
class TransitionVersionRequest extends FormRequest
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
        return ['to' => ['required', 'in:under_review,draft']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['to.*' => 'Status tujuan harus "under_review" atau "draft". Persetujuan terjadi lewat tanda tangan dan pemberlakuan lewat Document Control.'];
    }

    public function target(): VersionStatus
    {
        return VersionStatus::from($this->string('to')->toString());
    }
}
