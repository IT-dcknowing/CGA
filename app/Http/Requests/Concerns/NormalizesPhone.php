<?php

namespace App\Http\Requests\Concerns;

use App\Support\PhoneNumber;

/**
 * Accepte « 07 00 00 00 01 », « +225 0700000001 », « 00225… » et compare au format enregistré.
 */
trait NormalizesPhone
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function phoneRules(): array
    {
        return ['required', 'string', 'regex:'.PhoneNumber::PATTERN];
    }
}
