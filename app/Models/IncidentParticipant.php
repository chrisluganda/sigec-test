<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentParticipant extends Model
{
    use HasFactory;

    protected $fillable = ['incident_id', 'detainee_id', 'visitor_id', 'user_id', 'involvement_role'];
    public function incident(): BelongsTo { return $this->belongsTo(Incident::class); }
    public function detainee(): BelongsTo { return $this->belongsTo(Detainee::class); }
    public function visitor(): BelongsTo { return $this->belongsTo(Visitor::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
