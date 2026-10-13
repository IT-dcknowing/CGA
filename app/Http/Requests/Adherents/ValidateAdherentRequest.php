<?php

namespace App\Http\Requests\Adherents;

use App\Models\Adherent;
use App\Support\ActiveSpace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateAdherentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Un dossier repris d'un ancien registre garde son numéro historique (Q26) ;
     * sinon le numéro suit la séquence du CGA.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'historical_member_no' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique(Adherent::class, 'member_no')->where('cga_id', app(ActiveSpace::class)->cgaId()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['historical_member_no' => "numéro d'adhésion historique"];
    }
}
