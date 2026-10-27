<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $establishment_building_id
 * @property string $code
 * @property string|null $name
 * @property int $capacity
 * @property string $status
 * @property-read int $occupied
 */
class Cell extends Model
{
    protected $fillable = ['establishment_building_id', 'code', 'name', 'capacity', 'cell_type', 'status', 'notes'];

    /** @return BelongsTo<EstablishmentBuilding, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(EstablishmentBuilding::class, 'establishment_building_id');
    }

    /** @return HasMany<CellAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(CellAssignment::class);
    }

    /** @return HasMany<CellAssignment, $this> */
    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('ended_at');
    }
}
