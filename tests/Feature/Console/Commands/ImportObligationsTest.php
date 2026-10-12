<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Obligation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ImportObligationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_loads_the_bundled_referential_by_default(): void
    {
        $this->artisan('obligations:import')
            ->expectsOutput('Référentiel 2026-08-08 chargé : 42 obligations en vigueur.')
            ->assertSuccessful();

        $this->assertSame(42, Obligation::national()->count());
    }

    public function test_fails_with_a_message_when_the_file_is_missing(): void
    {
        $this->artisan('obligations:import', ['path' => 'introuvable.json'])
            ->expectsOutput('Fichier introuvable : introuvable.json')
            ->assertFailed();
    }
}
