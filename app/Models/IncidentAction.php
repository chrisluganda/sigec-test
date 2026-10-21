<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentAction extends Model
{
    use HasFactory;

    protected $fillable = ['incident_id', 'action_at', 'action_type', 'description', 'recorded_by'];
    protected function casts(): array { return ['action_at' => 'datetime', 'description' => 'encrypted']; }
    public function incident(): BelongsTo { return $this->belongsTo(Incident::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
