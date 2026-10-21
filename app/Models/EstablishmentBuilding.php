<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstablishmentBuilding extends Model
{
    protected $fillable = ['establishment_id', 'code', 'name', 'building_type', 'status', 'notes'];

    public function establishment(): BelongsTo { return $this->belongsTo(Establishment::class); }
    public function cells(): HasMany { return $this->hasMany(Cell::class); }
}
