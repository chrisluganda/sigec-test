<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cell extends Model
{
    protected $fillable = ['establishment_building_id', 'code', 'name', 'capacity', 'cell_type', 'status', 'notes'];

    public function building(): BelongsTo { return $this->belongsTo(EstablishmentBuilding::class, 'establishment_building_id'); }
    public function assignments(): HasMany { return $this->hasMany(CellAssignment::class); }
    public function activeAssignments(): HasMany { return $this->assignments()->whereNull('ended_at'); }
}
