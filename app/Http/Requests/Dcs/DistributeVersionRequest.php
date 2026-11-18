<?php

namespace App\Http\Requests\Dcs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DistributeVersionRequest extends FormRequest
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
        return ['userIds' => ['required', 'array', 'min:1'], 'userIds.*' => ['string', 'max:64'], 'mandatory' => ['nullable']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['userIds.*' => 'Pilih minimal satu penerima'];
    }

    /**
     * @return list<string>
     */
    public function userIds(): array
    {
        return array_values(array_map('strval', $this->input('userIds')));
    }

    /**
     * Mandatory unless the client says `false` explicitly, as in the Node API.
     */
    public function mandatory(): bool
    {
        return $this->input('mandatory') !== false;
    }
}
