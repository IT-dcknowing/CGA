<?php

namespace App\Console\Commands;

use App\Support\ObligationReferentialImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('obligations:import {path? : Fichier JSON du référentiel (par défaut database/data/legal-flow.json)}')]
#[Description('Charge une version du référentiel des obligations Legal Flow (Q44)')]
class ImportObligations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ObligationReferentialImporter $importer): int
    {
        try {
            $referential = $importer->import($this->argument('path') ?? ObligationReferentialImporter::defaultPath());
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Référentiel {$referential->version} chargé : {$referential->obligations()->where('is_active', true)->count()} obligations en vigueur.");

        return self::SUCCESS;
    }
}
