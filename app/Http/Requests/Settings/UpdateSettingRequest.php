<?php

namespace App\Http\Requests\Settings;

use App\Enums\SettingKey;
use App\Enums\SettingLevel;
use App\Enums\Space;
use App\Exceptions\ApiException;
use App\Support\ActiveSpace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    /**
     * Le CNCGA fixe la valeur du réseau pour tout paramètre ; un gérant ne modifie que
     * les paramètres adaptables par CGA.
     */
    public function authorize(): bool
    {
        return app(ActiveSpace::class)->space() === Space::Cncga
            || $this->settingKey()->definition()->level === SettingLevel::Cga;
    }

    protected function failedAuthorization(): void
    {
        throw ApiException::settingNotEditable();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $definition = $this->settingKey()->definition();

        return array_filter([
            'value' => ['required', ...$definition->rules],
            'value.*' => $definition->itemRules,
        ]);
    }

    public function settingKey(): SettingKey
    {
        return $this->route('key');
    }
}
