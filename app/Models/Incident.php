<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = ['establishment_id', 'offline_reference', 'incident_type', 'severity', 'occurred_at', 'location', 'description', 'immediate_action', 'status', 'reported_by', 'resolved_by', 'resolved_at'];
    protected function casts(): array { return ['occurred_at' => 'datetime', 'resolved_at' => 'datetime', 'description' => 'encrypted', 'immediate_action' => 'encrypted']; }
    public function establishment(): BelongsTo { return $this->belongsTo(Establishment::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function participants(): HasMany { return $this->hasMany(IncidentParticipant::class); }
    public function actions(): HasMany { return $this->hasMany(IncidentAction::class); }
}
