<?php

namespace App\Http\Requests\Obligations;

use App\Models\Obligation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddObligationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Une obligation nationale en vigueur du référentiel.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'obligation_id' => [
                'required',
                'integer',
                Rule::exists(Obligation::class, 'id')->whereNull('cga_id')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['obligation_id' => 'obligation'];
    }
}
