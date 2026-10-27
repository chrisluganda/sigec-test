<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $detainee_id
 * @property int $detention_case_id
 * @property int $establishment_id
 * @property int|null $detention_order_id
 * @property Carbon $admitted_at
 * @property Carbon|null $released_at
 */
class DetentionStay extends Model
{
    /** @use HasFactory<Factory<DetentionStay>> */
    use HasFactory;

    protected $fillable = [
        'detainee_id', 'detention_case_id', 'establishment_id', 'detention_order_id',
        'previous_stay_id', 'unit_reference', 'admitted_at', 'released_at', 'release_reason',
        'release_authority_reference', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['admitted_at' => 'datetime', 'released_at' => 'datetime'];
    }

    /** @return BelongsTo<Detainee, $this> */
    /** @return BelongsTo<Detainee, $this> */
    public function detainee(): BelongsTo
    {
        return $this->belongsTo(Detainee::class);
    }

    /** @return BelongsTo<DetentionCase, $this> */
    /** @return BelongsTo<DetentionCase, $this> */
    public function detentionCase(): BelongsTo
    {
        return $this->belongsTo(DetentionCase::class);
    }

    /** @return BelongsTo<Establishment, $this> */
    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /** @return BelongsTo<DetentionOrder, $this> */
    /** @return BelongsTo<DetentionOrder, $this> */
    public function detentionOrder(): BelongsTo
    {
        return $this->belongsTo(DetentionOrder::class);
    }

    /** @return HasMany<CellAssignment, $this> */
    /** @return HasMany<CellAssignment, $this> */
    public function cellAssignments(): HasMany
    {
        return $this->hasMany(CellAssignment::class);
    }

    /** @return BelongsTo<DetentionStay, $this> */
    /** @return BelongsTo<DetentionStay, $this> */
    public function previousStay(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_stay_id');
    }
}
