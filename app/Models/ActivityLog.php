<?php

namespace App\Models;

use App\Support\ActiveSpace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Journal d'audit (Q16) : qui a fait quoi, sur quelle donnée, dans quel CGA.
 */
#[Table('activity_log')]
#[Fillable(['causer_id', 'membership_id', 'cga_id', 'event', 'properties', 'ip_address'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * Inscrit un événement au journal. L'auteur, l'espace actif et le CGA sont repris
     * de la requête en cours quand ils ne sont pas fournis.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $event, ?Model $subject = null, array $properties = [], ?User $causer = null): self
    {
        $activeSpace = app(ActiveSpace::class);

        $log = new self([
            'causer_id' => $causer?->getKey() ?? auth()->id(),
            'membership_id' => $activeSpace->membership()?->getKey(),
            'cga_id' => self::cgaIdOf($subject) ?? $activeSpace->cgaId(),
            'event' => $event,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => request()->ip(),
        ]);

        $log->subject()->associate($subject)->save();

        return $log;
    }

    private static function cgaIdOf(?Model $subject): ?int
    {
        return match (true) {
            $subject === null => null,
            $subject instanceof Cga => $subject->getKey(),
            default => $subject->getAttributes()['cga_id'] ?? null,
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Cga, $this>
     */
    public function cga(): BelongsTo
    {
        return $this->belongsTo(Cga::class);
    }
}
