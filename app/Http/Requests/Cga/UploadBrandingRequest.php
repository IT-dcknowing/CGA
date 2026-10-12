<?php

namespace App\Http\Requests\Cga;

use App\Enums\BrandingAsset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadBrandingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Logo, signature et cachet : un ou plusieurs fichiers PNG ou JPEG de 2 Mo au plus.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assets = array_column(BrandingAsset::cases(), 'value');

        return collect($assets)->mapWithKeys(fn (string $asset): array => [$asset => [
            'nullable',
            'required_without_all:'.implode(',', array_diff($assets, [$asset])),
            'image',
            'mimes:png,jpg,jpeg',
            'max:2048',
        ]])->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return collect(BrandingAsset::cases())
            ->mapWithKeys(fn (BrandingAsset $asset): array => [
                "{$asset->value}.required_without_all" => 'Envoyez au moins un fichier : logo, signature ou cachet.',
            ])
            ->all();
    }
}
