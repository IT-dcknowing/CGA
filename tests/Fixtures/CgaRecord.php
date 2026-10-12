<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToCga;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Donnée propre à un CGA, utilisée pour tester le cloisonnement avant l'arrivée des vrais modules.
 * cga_id est volontairement assignable pour vérifier qu'il est ignoré dans un espace CGA.
 */
#[Table('cga_records')]
#[Fillable(['cga_id', 'label'])]
class CgaRecord extends Model
{
    use BelongsToCga;

    public static function createTable(): void
    {
        Schema::create('cga_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cga_id')->constrained();
            $table->string('label');
            $table->timestamps();
        });
    }
}
