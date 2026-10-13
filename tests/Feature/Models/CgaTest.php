<?php

namespace Tests\Feature\Models;

use App\Enums\CgaStatus;
use App\Models\Cga;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CgaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pending_cga_becomes_active_once_its_approval_is_recorded(): void
    {
        $cga = Cga::factory()->pending()->create();

        $cga->update(['approval_no' => '000/DEMO/DGI', 'approval_date' => '2018-03-29']);

        $this->assertSame(CgaStatus::Active, $cga->fresh()->status);
    }

    public function test_pending_cga_with_partial_approval_stays_pending(): void
    {
        $cga = Cga::factory()->pending()->create();

        $cga->update(['approval_no' => '000/DEMO/DGI']);

        $this->assertSame(CgaStatus::Pending, $cga->fresh()->status);
    }

    public function test_suspended_cga_stays_suspended_when_its_approval_is_updated(): void
    {
        $cga = Cga::factory()->suspended()->create();

        $cga->update(['approval_no' => '000/DEMO/DGI', 'approval_date' => '2018-03-29']);

        $this->assertSame(CgaStatus::Suspended, $cga->fresh()->status);
    }
}
