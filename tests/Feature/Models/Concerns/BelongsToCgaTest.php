<?php

namespace Tests\Feature\Models\Concerns;

use App\Enums\Role;
use App\Models\Cga;
use App\Models\Membership;
use App\Models\Scopes\CgaScope;
use App\Support\ActiveSpace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\CgaRecord;
use Tests\TestCase;

class BelongsToCgaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CgaRecord::createTable();
    }

    /**
     * @return array<string, array{Role}>
     */
    public static function rolesBoundToACga(): array
    {
        return [
            'cga agent' => [Role::Gerant],
            'adherent' => [Role::Adherent],
        ];
    }

    #[DataProvider('rolesBoundToACga')]
    public function test_space_bound_to_a_cga_only_sees_records_of_that_cga(Role $role): void
    {
        [$cgaA, $cgaB] = Cga::factory()->count(2)->create();
        CgaRecord::create(['cga_id' => $cgaA->id, 'label' => 'A']);
        CgaRecord::create(['cga_id' => $cgaB->id, 'label' => 'B']);
        app(ActiveSpace::class)->set(Membership::factory()->for($cgaA)->role($role)->create());

        $labels = CgaRecord::pluck('label')->all();

        $this->assertSame(['A'], $labels);
    }

    public function test_cga_space_cannot_find_a_record_of_another_cga_by_id(): void
    {
        [$cgaA, $cgaB] = Cga::factory()->count(2)->create();
        $recordOfB = CgaRecord::create(['cga_id' => $cgaB->id, 'label' => 'B']);
        app(ActiveSpace::class)->set(Membership::factory()->for($cgaA)->create());

        $found = CgaRecord::find($recordOfB->id);

        $this->assertNull($found);
    }

    public function test_cncga_space_sees_no_detailed_records_unless_scope_is_removed(): void
    {
        CgaRecord::create(['cga_id' => Cga::factory()->create()->id, 'label' => 'A']);
        app(ActiveSpace::class)->set(Membership::factory()->role(Role::Presidente)->create());

        $this->assertSame(0, CgaRecord::count());
        $this->assertSame(1, CgaRecord::withoutGlobalScope(CgaScope::class)->count());
    }

    public function test_all_records_are_visible_without_active_space(): void
    {
        CgaRecord::create(['cga_id' => Cga::factory()->create()->id, 'label' => 'A']);
        CgaRecord::create(['cga_id' => Cga::factory()->create()->id, 'label' => 'B']);

        $count = CgaRecord::count();

        $this->assertSame(2, $count);
    }

    public function test_creation_is_attached_to_active_cga_even_when_another_is_given(): void
    {
        [$cgaA, $cgaB] = Cga::factory()->count(2)->create();
        app(ActiveSpace::class)->set(Membership::factory()->for($cgaA)->create());

        $record = CgaRecord::create(['cga_id' => $cgaB->id, 'label' => 'A']);

        $this->assertSame($cgaA->id, $record->fresh()->cga_id);
    }

    public function test_moving_a_record_to_another_cga_is_refused_in_an_active_space(): void
    {
        [$cgaA, $cgaB] = Cga::factory()->count(2)->create();
        app(ActiveSpace::class)->set(Membership::factory()->for($cgaA)->create());
        $record = CgaRecord::create(['label' => 'A']);

        $this->expectException(LogicException::class);

        $record->update(['cga_id' => $cgaB->id]);
    }
}
