<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Cga;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration pour le poste local. Mot de passe de tous les comptes : « password ».
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $cgaAbidjan = Cga::factory()->create(['name' => 'CGA Démo Abidjan', 'abbreviation' => 'DEMOA']);
        $cgaBouake = Cga::factory()->create(['name' => 'CGA Démo Bouaké', 'abbreviation' => 'DEMOB']);

        $presidente = User::factory()->create(['name' => 'Présidente CNCGA', 'phone' => '0700000001']);
        Membership::factory()->for($presidente)->role(Role::Presidente)->create();

        $gerant = User::factory()->create(['name' => 'Gérant Démo Abidjan', 'phone' => '0700000002']);
        Membership::factory()->for($gerant)->for($cgaAbidjan)->create();

        $expert = User::factory()->create(['name' => 'Expert-comptable deux CGA', 'phone' => '0700000003']);
        Membership::factory()->for($expert)->for($cgaAbidjan)->role(Role::ExpertComptable)->primary()->create();
        Membership::factory()->for($expert)->for($cgaBouake)->role(Role::ExpertComptable)->create();
    }
}
