<?php

namespace App\Enums;

enum Role: string
{
    case Presidente = 'presidente';
    case AgentCncga = 'agent_cncga';
    case MembreBureau = 'membre_bureau';
    case Gerant = 'gerant';
    case ExpertComptable = 'expert_comptable';
    case Comptable = 'comptable';
    case Secretaire = 'secretaire';
    case Juriste = 'juriste';
    case Adherent = 'adherent';

    public function space(): Space
    {
        return match ($this) {
            self::Presidente, self::AgentCncga, self::MembreBureau => Space::Cncga,
            self::Gerant, self::ExpertComptable, self::Comptable, self::Secretaire, self::Juriste => Space::Cga,
            self::Adherent => Space::Adherent,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Presidente => 'Présidente',
            self::AgentCncga => 'Agent CNCGA',
            self::MembreBureau => 'Membre du bureau',
            self::Gerant => 'Gérant',
            self::ExpertComptable => 'Expert-comptable',
            self::Comptable => 'Comptable',
            self::Secretaire => 'Secrétaire',
            self::Juriste => 'Juriste',
            self::Adherent => 'Adhérent',
        };
    }
}
