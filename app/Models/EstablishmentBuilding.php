<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $establishment_id
 * @property string $code
 * @property string $name
 * @property string $status
 */
class EstablishmentBuilding extends Model
{
    protected $fillable = ['establishment_id', 'code', 'name', 'building_type', 'status', 'notes'];

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /** @return HasMany<Cell, $this> */
    public function cells(): HasMany
    {
        return $this->hasMany(Cell::class);
    }
}
