<?php

namespace Tests\Feature\Models\Concerns;

use App\Models\ActivityLog;
use App\Models\Cga;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuditableTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_records_creation_with_causer_and_cga(): void
    {
        $agent = User::factory()->create();
        $this->actingAs($agent);

        $cga = Cga::factory()->create(['name' => 'CGA Test']);

        $log = $this->logFor('created', $cga);
        $this->assertSame($agent->id, $log->causer_id);
        $this->assertSame($cga->id, $log->cga_id);
        $this->assertSame('CGA Test', $log->properties['attributes']['name']);
    }

    public function test_records_old_and_new_values_of_changed_attributes_only(): void
    {
        $cga = Cga::factory()->create(['name' => 'Ancien nom']);

        $cga->update(['name' => 'Nouveau nom']);

        $this->assertSame(
            ['old' => ['name' => 'Ancien nom'], 'new' => ['name' => 'Nouveau nom']],
            $this->logFor('updated', $cga)->properties,
        );
    }

    public function test_never_records_hidden_attributes(): void
    {
        $user = User::factory()->create();

        $user->update(['name' => 'Nouveau nom', 'password' => 'nouveau-secret']);

        $this->assertArrayNotHasKey('password', $this->logFor('created', $user)->properties['attributes']);
        $this->assertSame(['name' => 'Nouveau nom'], $this->logFor('updated', $user)->properties['new']);
    }

    public function test_skips_update_that_only_changes_hidden_attributes(): void
    {
        $user = User::factory()->create();

        $user->update(['password' => 'nouveau-secret']);

        $this->assertSame(0, ActivityLog::where('event', 'updated')->whereMorphedTo('subject', $user)->count());
    }

    public function test_records_deletion_in_the_cga_of_the_deleted_record(): void
    {
        $membership = Membership::factory()->create();

        $membership->delete();

        $this->assertSame($membership->cga_id, $this->logFor('deleted', $membership)->cga_id);
    }

    private function logFor(string $event, Model $subject): ActivityLog
    {
        return ActivityLog::where('event', $event)->whereMorphedTo('subject', $subject)->sole();
    }
}
