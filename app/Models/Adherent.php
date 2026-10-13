<?php

namespace App\Models;

use App\Enums\AdherentStatus;
use App\Enums\LegalForm;
use App\Enums\TurnoverBasis;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCga;
use App\Support\PhoneNumber;
use Database\Factories\AdherentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Dossier d'un adhérent, propre à un CGA. Le statut, le numéro et la validation ne sont
 * jamais remplis par assignation de masse : ils passent par App\Support\Adherents.
 */
#[Fillable([
    'legal_form',
    'company_name',
    'trade_name',
    'ncc',
    'rccm',
    'idu',
    'regime_id',
    'sector_id',
    'activity',
    'tax_center_id',
    'address',
    'commune',
    'plot_reference',
    'phone',
    'email',
    'signatory_name',
    'signatory_title',
    'turnover',
    'turnover_basis',
    'employees',
    'owner_last_name',
    'owner_first_names',
    'owner_nationality',
    'owner_id_document',
    'membership_fee',
    'joined_at',
    'cmu_dependents',
    'sells_to_consumers',
    'uses_measuring_instruments',
    'has_expatriates',
    'has_interns',
    'has_sme_status',
])]
class Adherent extends Model
{
    /** @use HasFactory<AdherentFactory> */
    use Auditable, BelongsToCga, HasFactory;

    /**
     * Champs exigés pour valider un dossier (Q22, plus le NCC et l'activité de la maquette).
     */
    public const REQUIRED_FOR_VALIDATION = [
        'legal_form',
        'company_name',
        'ncc',
        'regime_id',
        'sector_id',
        'activity',
        'tax_center_id',
        'address',
        'phone',
        'signatory_name',
        'turnover',
        'turnover_basis',
        'joined_at',
    ];

    /**
     * Champs exigés en plus pour une entreprise individuelle (Q29).
     */
    public const REQUIRED_FOR_INDIVIDUAL = [
        'owner_last_name',
        'owner_first_names',
        'owner_nationality',
        'owner_id_document',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'cmu_dependents' => 0,
        'sells_to_consumers' => false,
        'uses_measuring_instruments' => false,
        'has_expatriates' => false,
        'has_interns' => false,
        'has_sme_status' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AdherentStatus::class,
            'legal_form' => LegalForm::class,
            'turnover_basis' => TurnoverBasis::class,
            'turnover' => 'integer',
            'employees' => 'integer',
            'membership_fee' => 'integer',
            'member_seq' => 'integer',
            'joined_at' => 'date',
            'validated_at' => 'datetime',
            'cmu_dependents' => 'integer',
            'sells_to_consumers' => 'boolean',
            'uses_measuring_instruments' => 'boolean',
            'has_expatriates' => 'boolean',
            'has_interns' => 'boolean',
            'has_sme_status' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : PhoneNumber::normalize($value));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function ncc(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : mb_strtoupper(trim($value)));
    }

    public function isIndividual(): bool
    {
        return $this->legal_form === LegalForm::EntrepriseIndividuelle;
    }

    /**
     * Champs encore vides qui empêchent la validation du dossier.
     *
     * @return list<string>
     */
    public function missingForValidation(): array
    {
        $required = $this->isIndividual()
            ? [...self::REQUIRED_FOR_VALIDATION, ...self::REQUIRED_FOR_INDIVIDUAL]
            : self::REQUIRED_FOR_VALIDATION;

        return array_values(array_filter(
            $required,
            fn (string $field): bool => blank($this->getAttribute($field)),
        ));
    }

    /**
     * @return BelongsTo<Regime, $this>
     */
    public function regime(): BelongsTo
    {
        return $this->belongsTo(Regime::class);
    }

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return BelongsTo<TaxCenter, $this>
     */
    public function taxCenter(): BelongsTo
    {
        return $this->belongsTo(TaxCenter::class);
    }

    /**
     * @return HasMany<AdherentRegime, $this>
     */
    public function regimeHistory(): HasMany
    {
        return $this->hasMany(AdherentRegime::class)->orderBy('effective_date')->orderBy('id');
    }

    /**
     * Dernier changement de régime : sa date fait courir le délai de régularisation au RSI (Q31).
     *
     * @return HasOne<AdherentRegime, $this>
     */
    public function latestRegimeChange(): HasOne
    {
        return $this->hasOne(AdherentRegime::class)->latestOfMany();
    }

    /**
     * @return HasMany<StrikeOff, $this>
     */
    public function strikeOffs(): HasMany
    {
        return $this->hasMany(StrikeOff::class);
    }

    /**
     * Radiation en vigueur (non annulée).
     *
     * @return HasOne<StrikeOff, $this>
     */
    public function activeStrikeOff(): HasOne
    {
        return $this->hasOne(StrikeOff::class)->ofMany(['id' => 'max'], fn ($query) => $query->whereNull('cancelled_at'));
    }

    /**
     * Obligations attribuées à l'adhérent (Q45).
     *
     * @return HasMany<AdherentObligation, $this>
     */
    public function adherentObligations(): HasMany
    {
        return $this->hasMany(AdherentObligation::class);
    }

    /**
     * Accès des personnes de l'entreprise (dirigeant, comptable interne…, Q11).
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
