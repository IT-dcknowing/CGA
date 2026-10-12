<?php

namespace Database\Seeders;

use App\Models\Regime;
use App\Models\Sector;
use App\Models\StrikeOffReason;
use App\Support\ObligationReferentialImporter;
use Illuminate\Database\Seeder;

/**
 * Référentiels nationaux, à charger aussi en production :
 * php artisan db:seed --class=ReferenceDataSeeder --force
 *
 * Relançable sans risque : chaque ligne est retrouvée par son code.
 */
class ReferenceDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ObligationReferentialImporter $importer): void
    {
        $this->upsert(Regime::class, [
            ['code' => 'RME', 'label' => 'Régime des microentreprises', 'allows_membership' => true],
            ['code' => 'TEE', 'label' => "Taxe d'État de l'entreprenant", 'allows_membership' => true],
            ['code' => 'RSI', 'label' => "Régime simplifié d'imposition", 'allows_membership' => false],
        ]);

        // Liste de la maquette (Q82), en attendant la nomenclature DGI des codes d'activité.
        $this->upsert(Sector::class, [
            ['code' => 'AGR001', 'label' => 'Agriculture'],
            ['code' => 'COM001', 'label' => 'Commerce général'],
            ['code' => 'BTP001', 'label' => 'Bâtiment et travaux publics'],
            ['code' => 'IND001', 'label' => 'Industrie / transformation'],
            ['code' => 'SER001', 'label' => 'Services'],
            ['code' => 'SAN001', 'label' => 'Santé / pharmacie'],
            ['code' => 'TRA001', 'label' => 'Transport'],
            ['code' => 'NUM001', 'label' => 'Numérique'],
        ]);

        // Article 6 du contrat d'adhésion (Q34, Q35).
        $this->upsert(StrikeOffReason::class, [
            ['code' => 'rupture_conventionnelle', 'label' => 'Rupture conventionnelle'],
            ['code' => 'reclassement_regime_reel', 'label' => 'Reclassement au régime réel'],
            ['code' => 'cessation_activite', 'label' => "Cessation d'activité"],
            ['code' => 'vente_cession_fonds', 'label' => 'Vente ou cession du fonds'],
            ['code' => 'demande_adherent', 'label' => "Demande de l'adhérent"],
            ['code' => 'manquements_graves', 'label' => 'Manquements graves', 'requires_document' => true],
            ['code' => 'perte_agrement_cga', 'label' => "Perte d'agrément du CGA"],
            ['code' => 'deces', 'label' => 'Décès'],
            ['code' => 'autre', 'label' => 'Autre', 'requires_details' => true],
        ]);

        $importer->import(ObligationReferentialImporter::defaultPath());
    }

    /**
     * @param  class-string<Regime|Sector|StrikeOffReason>  $model
     * @param  list<array<string, mixed>>  $rows
     */
    private function upsert(string $model, array $rows): void
    {
        foreach ($rows as $position => $row) {
            $model::updateOrCreate(['code' => $row['code']], [...$row, 'sort_order' => $position + 1]);
        }
    }
}
