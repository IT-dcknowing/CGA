<?php

namespace Database\Seeders;

use App\Enums\AdherentReport;
use App\Enums\LegalForm;
use App\Enums\Role;
use App\Enums\TurnoverBasis;
use App\Models\Adherent;
use App\Models\Cga;
use App\Models\Membership;
use App\Models\Regime;
use App\Models\Sector;
use App\Models\TaxRegion;
use App\Models\User;
use App\Support\AdherentObligations;
use App\Support\Adherents;
use App\Support\Deadlines;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration pour le poste local, utilisées par la collection Postman
 * (postman/CGA-Connect.postman_collection.json). Mot de passe de tous les comptes : « password ».
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        // Directions régionales et centres fictifs, en attendant l'annuaire DGI.
        $regionAbidjan = TaxRegion::create(['code' => 'DEMO-DR1', 'label' => 'Direction régionale Démo Abidjan', 'city' => 'Abidjan']);
        $regionBouake = TaxRegion::create(['code' => 'DEMO-DR2', 'label' => 'Direction régionale Démo Bouaké', 'city' => 'Bouaké']);
        $centerPlateau = $regionAbidjan->centers()->create(['code' => 'DEMO-CDI1', 'label' => 'Centre des impôts Démo Plateau']);
        $regionAbidjan->centers()->create(['code' => 'DEMO-CDI2', 'label' => 'Centre des impôts Démo Cocody']);
        $regionBouake->centers()->create(['code' => 'DEMO-CDI3', 'label' => 'Centre des impôts Démo Bouaké']);

        $cgaAbidjan = Cga::factory()->create([
            'name' => 'CGA Démo Abidjan',
            'abbreviation' => 'DEMOA',
            'city' => 'Abidjan',
            'approval_no' => '000/DEMO/DGI',
            'approval_date' => '2020-01-15',
            'approval_expires_at' => '2030-01-15',
            'tax_region_id' => $regionAbidjan->id,
            'tax_center_id' => $centerPlateau->id,
        ]);
        $cgaBouake = Cga::factory()->pending()->create([
            'name' => 'CGA Démo Bouaké',
            'abbreviation' => 'DEMOB',
            'city' => 'Bouaké',
        ]);
        $cgaSuspended = Cga::factory()->create([
            'name' => 'CGA Démo Suspendu',
            'abbreviation' => 'DEMOS',
            'city' => 'Daloa',
            'approval_no' => '001/DEMO/DGI',
            'approval_date' => '2019-06-01',
        ]);
        $cgaSuspended->suspend('Démonstration : cotisation impayée depuis 90 jours');

        $this->account('0700000001', 'Présidente CNCGA', Role::Presidente);
        $gerant = $this->account('0700000002', 'Gérant Démo Abidjan', Role::Gerant, $cgaAbidjan);

        [$bakery] = $this->demoAdherents($cgaAbidjan, $cgaBouake, $gerant, $centerPlateau->id);

        $expert = $this->account('0700000003', 'Expert-comptable deux CGA', Role::ExpertComptable, $cgaAbidjan, primary: true);
        Membership::factory()->for($expert)->for($cgaBouake)->role(Role::ExpertComptable)->create();

        $this->account('0700000004', 'Agent CNCGA', Role::AgentCncga);
        $this->account('0700000005', 'Membre du bureau CNCGA', Role::MembreBureau);
        $this->account('0700000006', 'Comptable Démo Abidjan', Role::Comptable, $cgaAbidjan);
        $owner = $this->account('0700000007', 'Adhérent Démo Abidjan', Role::Adherent, $cgaAbidjan, adherent: $bakery);

        // Une déclaration signalée par l'adhérent, en attente de confirmation par le CGA (Q47).
        $first = app(Deadlines::class)->forAdherent($bakery, CarbonImmutable::today(), 3)->first();
        app(Deadlines::class)->report($bakery, $first['obligation'], CarbonImmutable::parse($first['due_date']), AdherentReport::Declared, null, $owner);
        $this->account('0700000008', 'Gérant Démo Suspendu', Role::Gerant, $cgaSuspended);

        User::factory()->disabled()->create(['name' => 'Compte désactivé', 'phone' => '0700000009']);
        User::factory()->withoutPassword()->create(['name' => 'Invité sans mot de passe', 'phone' => '0700000010']);
    }

    private function account(string $phone, string $name, Role $role, ?Cga $cga = null, bool $primary = false, ?Adherent $adherent = null): User
    {
        $user = User::factory()->create(['name' => $name, 'phone' => $phone]);

        Membership::factory()
            ->for($user)
            ->role($role)
            ->when($cga !== null, fn ($factory) => $factory->for($cga))
            ->when($primary, fn ($factory) => $factory->primary())
            ->create($adherent === null ? [] : ['adherent_id' => $adherent->id]);

        return $user;
    }

    /**
     * Deux dossiers validés et un brouillon dans DEMOA, un brouillon dans DEMOB (en cours d'agrément,
     * il ne peut pas encore valider de dossier). Les obligations proposées par le moteur sont
     * confirmées pour les deux dossiers validés. Données fictives.
     *
     * @return list<Adherent>
     */
    private function demoAdherents(Cga $cgaAbidjan, Cga $cgaBouake, User $gerant, int $taxCenterId): array
    {
        $adherents = app(Adherents::class);
        $obligations = app(AdherentObligations::class);
        $regimes = Regime::pluck('id', 'code');
        $sectors = Sector::pluck('id', 'code');

        $bakery = $adherents->createDraft([
            'legal_form' => LegalForm::Sarl,
            'company_name' => 'Boulangerie Le Bon Pain',
            'ncc' => '0000001 A',
            'regime_id' => $regimes['RME'],
            'sector_id' => $sectors['COM001'],
            'activity' => 'Boulangerie-pâtisserie',
            'tax_center_id' => $taxCenterId,
            'address' => 'Treichville, Abidjan',
            'commune' => 'Treichville',
            'phone' => '0101010101',
            'signatory_name' => 'Adhérent Démo Abidjan',
            'signatory_title' => 'Gérant',
            'turnover' => 18_000_000,
            'turnover_basis' => TurnoverBasis::PreviousYear,
            'employees' => 4,
            'sells_to_consumers' => true,
            'uses_measuring_instruments' => true,
            'joined_at' => '2026-02-10',
        ], $cgaAbidjan, $gerant);
        $adherents->validate($bakery, $gerant);
        $obligations->confirmProposals($bakery, $gerant);

        $individual = $adherents->createDraft([
            'legal_form' => LegalForm::EntrepriseIndividuelle,
            'company_name' => 'Kouamé Services',
            'ncc' => '0000002 B',
            'regime_id' => $regimes['TEE'],
            'sector_id' => $sectors['SER001'],
            'activity' => 'Maintenance informatique',
            'tax_center_id' => $taxCenterId,
            'address' => 'Adjamé, Abidjan',
            'phone' => '0202020202',
            'signatory_name' => 'Kouamé Démo',
            'turnover' => 6_000_000,
            'turnover_basis' => TurnoverBasis::Forecast,
            'owner_last_name' => 'Kouamé',
            'owner_first_names' => 'Démo',
            'owner_nationality' => 'Ivoirienne',
            'owner_id_document' => 'CNI C0000000000',
            'joined_at' => '2026-03-01',
        ], $cgaAbidjan, $gerant);
        $adherents->validate($individual, $gerant);
        $obligations->confirmProposals($individual, $gerant);

        $draft = $adherents->createDraft([
            'legal_form' => LegalForm::Sarl,
            'company_name' => 'Atelier Couture Awa',
            'phone' => '0303030303',
        ], $cgaAbidjan, $gerant);

        $adherents->createDraft(['company_name' => 'Maquis Démo Bouaké', 'ncc' => '0000003 C'], $cgaBouake, $gerant);

        return [$bakery, $individual, $draft];
    }
}
