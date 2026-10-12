<?php

namespace App\Enums;

/**
 * Éléments d'identité visuelle d'un CGA, indispensables aux documents (Q17, Q75).
 */
enum BrandingAsset: string
{
    case Logo = 'logo';
    case Signature = 'signature';
    case Stamp = 'stamp';

    public function column(): string
    {
        return $this->value.'_path';
    }
}
