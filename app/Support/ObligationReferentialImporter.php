<?php

namespace App\Support;

use App\Models\Obligation;
use App\Models\ObligationReferential;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

/**
 * Charge une version du référentiel Legal Flow (fichier JSON). Les obligations nationales
 * sont créées ou mises à jour ; celles qui ont disparu du fichier sont désactivées, pas
 * supprimées, car des dossiers d'adhérents peuvent y faire référence.
 */
class ObligationReferentialImporter
{
    private const PERIODICITIES = ['P', 'M', 'T', 'A', 'C', 'E', 'MT'];

    public static function defaultPath(): string
    {
        return database_path('data/legal-flow.json');
    }

    /**
     * @throws InvalidArgumentException si le fichier est illisible, invalide, ou si sa
     *                                  version a déjà été chargée avec un autre contenu.
     */
    public function import(string $path): ObligationReferential
    {
        $raw = is_readable($path) ? (string) file_get_contents($path) : throw new InvalidArgumentException("Fichier introuvable : {$path}");
        $content = $this->decode($raw);
        $checksum = hash('sha256', $raw);

        $existing = ObligationReferential::firstWhere('version', $content['version']);

        if ($existing !== null && $existing->checksum !== $checksum) {
            throw new InvalidArgumentException(
                "La version {$content['version']} a déjà été chargée avec un contenu différent : changez le numéro de version."
            );
        }

        return DB::transaction(function () use ($existing, $content, $checksum): ObligationReferential {
            $referential = $existing ?? ObligationReferential::create([
                'version' => $content['version'],
                'checksum' => $checksum,
                'content' => $content,
            ]);

            $codes = [];

            foreach ($content['organismes'] as $administration) {
                foreach ($administration['obligations'] as $obligation) {
                    $codes[] = $obligation['id'];

                    Obligation::firstOrNew(['cga_id' => null, 'code' => $obligation['id']])
                        ->forceFill([
                            'obligation_referential_id' => $referential->id,
                            'administration_code' => $administration['code'],
                            'administration_name' => $administration['nom'],
                            'label' => $obligation['libelle'],
                            'legal_basis' => $obligation['base'] ?? null,
                            'periodicity' => $obligation['periodicite'],
                            'due_day' => $obligation['jour'] ?? null,
                            'due_date' => $obligation['echeance'] ?? null,
                            'platform' => $obligation['plateforme'] ?? null,
                            'applicability' => $obligation['condition'] ?? null,
                            'periodicity_rule' => $obligation['periodicite_conditionnelle'] ?? null,
                            'is_active' => true,
                            'sort_order' => count($codes),
                        ])
                        ->save();
                }
            }

            Obligation::query()->whereNull('cga_id')->whereNotIn('code', $codes)->update(['is_active' => false]);

            return $referential;
        });
    }

    /**
     * @return array{version: string, organismes: list<array{code: string, nom: string, obligations: list<array<string, mixed>>}>}
     */
    private function decode(string $raw): array
    {
        try {
            $content = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Le référentiel n\'est pas un JSON valide : '.$e->getMessage(), previous: $e);
        }

        if (! is_string($content['version'] ?? null) || ! is_array($content['organismes'] ?? null)) {
            throw new InvalidArgumentException('Le référentiel doit contenir « version » et « organismes ».');
        }

        foreach ($content['organismes'] as $administration) {
            if (! isset($administration['code'], $administration['nom']) || ! is_array($administration['obligations'] ?? null)) {
                throw new InvalidArgumentException('Chaque organisme doit avoir « code », « nom » et « obligations ».');
            }

            foreach ($administration['obligations'] as $obligation) {
                if (! isset($obligation['id'], $obligation['libelle']) || ! in_array($obligation['periodicite'] ?? null, self::PERIODICITIES, true)) {
                    throw new InvalidArgumentException(sprintf(
                        'Obligation « %s » de « %s » : « id », « libelle » et une « periodicite » valide sont obligatoires.',
                        $obligation['id'] ?? '?',
                        $administration['code'],
                    ));
                }

                foreach (array_filter([$obligation['condition'] ?? null, $obligation['periodicite_conditionnelle']['si'] ?? null]) as $condition) {
                    $this->ensureConditionIsKnown($condition, $content, $obligation['id']);
                }
            }
        }

        return $content;
    }

    /**
     * Une condition qui vise un champ ou un seuil inconnu ferait disparaître une obligation
     * sans que personne ne le voie : le chargement est refusé.
     *
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $content
     */
    private function ensureConditionIsKnown(array $condition, array $content, string $obligationId): void
    {
        $fields = $content['profil_champs'] ?? [];
        $thresholds = $content['seuils'] ?? [];

        if (! in_array($condition['champ'] ?? null, $fields, true)) {
            throw new InvalidArgumentException("Obligation « {$obligationId} » : champ de profil inconnu « ".($condition['champ'] ?? '?').' ».');
        }

        if (isset($condition['seuil']) && ! array_key_exists($condition['seuil'], $thresholds)) {
            throw new InvalidArgumentException("Obligation « {$obligationId} » : seuil inconnu « {$condition['seuil']} ».");
        }
    }
}
