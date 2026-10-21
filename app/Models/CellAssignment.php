<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CellAssignment extends Model
{
    protected $fillable = ['offline_reference', 'source_establishment_id', 'cell_id', 'detainee_id', 'detention_stay_id', 'assigned_by', 'assigned_at', 'ended_at', 'end_reason'];

    protected function casts(): array { return ['assigned_at' => 'datetime', 'ended_at' => 'datetime']; }
    public function cell(): BelongsTo { return $this->belongsTo(Cell::class); }
    public function detainee(): BelongsTo { return $this->belongsTo(Detainee::class); }
    public function detentionStay(): BelongsTo { return $this->belongsTo(DetentionStay::class); }
}
