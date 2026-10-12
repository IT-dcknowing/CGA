<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accepte « 07 00 00 00 01 », « +225 0700000001 », « 00225… » et compare au format enregistré.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::PATTERN],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.string' => "Le numéro de téléphone n'est pas valide.",
            'phone.regex' => "Le numéro de téléphone n'est pas valide.",
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.string' => "Le mot de passe n'est pas valide.",
            'device_name.string' => "Le nom de l'appareil n'est pas valide.",
            'device_name.max' => "Le nom de l'appareil ne doit pas dépasser 100 caractères.",
        ];
    }
}
