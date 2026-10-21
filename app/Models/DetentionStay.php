<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetentionStay extends Model
{
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

    public function detainee(): BelongsTo
    {
        return $this->belongsTo(Detainee::class);
    }

    public function detentionCase(): BelongsTo
    {
        return $this->belongsTo(DetentionCase::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function detentionOrder(): BelongsTo
    {
        return $this->belongsTo(DetentionOrder::class);
    }

    public function cellAssignments(): HasMany
    {
        return $this->hasMany(CellAssignment::class);
    }

    public function previousStay(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_stay_id');
    }
}

