<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use NormalizesPhone;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => $this->phoneRules(),
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
