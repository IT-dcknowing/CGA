<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Cga;
use App\Models\Membership;
use App\Models\TaxRegion;
use App\Models\User;
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
        $this->account('0700000002', 'Gérant Démo Abidjan', Role::Gerant, $cgaAbidjan);

        $expert = $this->account('0700000003', 'Expert-comptable deux CGA', Role::ExpertComptable, $cgaAbidjan, primary: true);
        Membership::factory()->for($expert)->for($cgaBouake)->role(Role::ExpertComptable)->create();

        $this->account('0700000004', 'Agent CNCGA', Role::AgentCncga);
        $this->account('0700000005', 'Membre du bureau CNCGA', Role::MembreBureau);
        $this->account('0700000006', 'Comptable Démo Abidjan', Role::Comptable, $cgaAbidjan);
        $this->account('0700000007', 'Adhérent Démo Abidjan', Role::Adherent, $cgaAbidjan);
        $this->account('0700000008', 'Gérant Démo Suspendu', Role::Gerant, $cgaSuspended);

        User::factory()->disabled()->create(['name' => 'Compte désactivé', 'phone' => '0700000009']);
        User::factory()->withoutPassword()->create(['name' => 'Invité sans mot de passe', 'phone' => '0700000010']);
    }

    private function account(string $phone, string $name, Role $role, ?Cga $cga = null, bool $primary = false): User
    {
        $user = User::factory()->create(['name' => $name, 'phone' => $phone]);

        Membership::factory()
            ->for($user)
            ->role($role)
            ->when($cga !== null, fn ($factory) => $factory->for($cga))
            ->when($primary, fn ($factory) => $factory->primary())
            ->create();

        return $user;
    }
}
