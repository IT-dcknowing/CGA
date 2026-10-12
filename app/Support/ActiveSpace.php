<?php

namespace App\Support;

use App\Enums\Role;
use App\Enums\Space;
use App\Models\Membership;

/**
 * Espace de travail de la requête en cours, défini par le middleware « espace.actif ».
 */
class ActiveSpace
{
    private ?Membership $membership = null;

    public function set(Membership $membership): void
    {
        $this->membership = $membership;
    }

    public function isSet(): bool
    {
        return $this->membership !== null;
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }

    public function space(): ?Space
    {
        return $this->membership?->space;
    }

    public function role(): ?Role
    {
        return $this->membership?->role;
    }

    public function cgaId(): ?int
    {
        return $this->membership?->cga_id;
    }
}
