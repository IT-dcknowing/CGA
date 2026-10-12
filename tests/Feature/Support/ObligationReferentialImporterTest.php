<?php

namespace Tests\Feature\Support;

use App\Models\Obligation;
use App\Models\ObligationReferential;
use App\Support\ObligationReferentialImporter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ObligationReferentialImporterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_imports_every_obligation_of_the_bundled_referential(): void
    {
        $referential = (new ObligationReferentialImporter)->import(ObligationReferentialImporter::defaultPath());

        $this->assertSame('2026-08-08', $referential->version);
        $this->assertSame(42, Obligation::national()->count());
        $this->assertSame(7, Obligation::national()->distinct()->count('administration_code'));
        $cnpsContributions = Obligation::firstWhere('code', 'cnps_cot');
        $this->assertSame('MT', $cnpsContributions->periodicity);
        $this->assertSame(['alors' => 'M', 'sinon' => 'T'], array_intersect_key($cnpsContributions->periodicity_rule, ['alors' => 0, 'sinon' => 0]));
    }

    public function test_reimporting_the_same_file_changes_nothing(): void
    {
        $importer = new ObligationReferentialImporter;
        $importer->import(ObligationReferentialImporter::defaultPath());

        $importer->import(ObligationReferentialImporter::defaultPath());

        $this->assertSame(1, ObligationReferential::count());
        $this->assertSame(42, Obligation::count());
    }

    public function test_new_version_deactivates_obligations_it_no_longer_contains(): void
    {
        $importer = new ObligationReferentialImporter;
        $importer->import(ObligationReferentialImporter::defaultPath());

        $newVersion = $importer->import($this->writeReferential('2027-01-01', [
            ['id' => 'ncc', 'libelle' => 'Déclaration fiscale d\'existence', 'base' => 'CGI', 'periodicite' => 'P'],
        ]));

        $this->assertSame(['ncc'], Obligation::national()->pluck('code')->all());
        $this->assertSame($newVersion->id, Obligation::firstWhere('code', 'ncc')->obligation_referential_id);
        $this->assertSame(42, Obligation::count());
    }

    public function test_refuses_a_known_version_with_different_content(): void
    {
        $importer = new ObligationReferentialImporter;
        $importer->import($this->writeReferential('2027-01-01', [
            ['id' => 'ncc', 'libelle' => 'Version initiale', 'periodicite' => 'P'],
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La version 2027-01-01 a déjà été chargée avec un contenu différent');

        $importer->import($this->writeReferential('2027-01-01', [
            ['id' => 'ncc', 'libelle' => 'Version modifiée', 'periodicite' => 'P'],
        ]));
    }

    public function test_refuses_an_obligation_with_unknown_periodicity_without_importing_anything(): void
    {
        $path = $this->writeReferential('2027-01-01', [
            ['id' => 'ncc', 'libelle' => 'Déclaration', 'periodicite' => 'Z'],
        ]);

        try {
            (new ObligationReferentialImporter)->import($path);
            $this->fail('Le référentiel invalide aurait dû être refusé.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('« ncc »', $e->getMessage());
        }

        $this->assertSame(0, ObligationReferential::count());
    }

    /**
     * @param  list<array<string, mixed>>  $obligations
     */
    private function writeReferential(string $version, array $obligations): string
    {
        $path = tempnam(sys_get_temp_dir(), 'legal-flow');
        file_put_contents($path, json_encode([
            'version' => $version,
            'organismes' => [['code' => 'dgi', 'nom' => 'Direction Générale des Impôts', 'obligations' => $obligations]],
        ]));
        $this->beforeApplicationDestroyed(fn () => unlink($path));

        return $path;
    }
}
