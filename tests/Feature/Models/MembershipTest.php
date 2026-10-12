<?php

namespace Tests\Feature\Models;

use App\Enums\Role;
use App\Enums\Space;
use App\Models\Cga;
use App\Models\Membership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_space_is_derived_from_role_whatever_is_given(): void
    {
        $membership = Membership::factory()->role(Role::ExpertComptable)->create(['space' => Space::Cncga]);

        $this->assertSame(Space::Cga, $membership->fresh()->space);
    }

    public function test_cga_role_without_cga_is_refused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Le rôle « gerant » exige un CGA.');

        Membership::factory()->role(Role::Gerant)->create(['cga_id' => null]);
    }

    public function test_cncga_role_with_a_cga_is_refused(): void
    {
        $cga = Cga::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Le rôle « presidente » interdit un CGA.');

        Membership::factory()->role(Role::Presidente)->create(['cga_id' => $cga->id]);
    }
}
