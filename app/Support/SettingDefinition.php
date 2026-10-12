<?php

namespace App\Support;

use App\Enums\SettingLevel;

final readonly class SettingDefinition
{
    /**
     * @param  array<int, mixed>  $rules  Règles de validation de la valeur.
     * @param  array<int, mixed>  $itemRules  Règles de chaque élément quand la valeur est une liste.
     * @param  string  $question  Question du questionnaire du Directeur d'où vient la valeur.
     * @param  bool  $toBeConfirmed  Valeur provisoire en attente d'un arbitrage externe.
     */
    public function __construct(
        public string $label,
        public mixed $default,
        public array $rules,
        public SettingLevel $level,
        public string $question,
        public bool $toBeConfirmed = false,
        public array $itemRules = [],
    ) {}
}
