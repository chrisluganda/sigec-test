<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrisonVisit extends Model
{
    use HasFactory;

    protected $fillable = ['detainee_id', 'establishment_id', 'visitor_id', 'visit_type', 'relationship', 'status', 'scheduled_at', 'arrived_at', 'ended_at', 'outcome', 'cancellation_reason', 'recorded_by', 'offline_reference'];
    protected function casts(): array { return ['scheduled_at' => 'datetime', 'arrived_at' => 'datetime', 'ended_at' => 'datetime', 'outcome' => 'encrypted', 'cancellation_reason' => 'encrypted']; }
    public function detainee(): BelongsTo { return $this->belongsTo(Detainee::class); }
    public function establishment(): BelongsTo { return $this->belongsTo(Establishment::class); }
    public function visitor(): BelongsTo { return $this->belongsTo(Visitor::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
