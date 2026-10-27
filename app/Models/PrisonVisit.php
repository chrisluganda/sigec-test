<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrisonVisit extends Model
{
    /** @use HasFactory<Factory<PrisonVisit>> */
    use HasFactory;

    protected $fillable = ['detainee_id', 'establishment_id', 'visitor_id', 'visit_type', 'relationship', 'status', 'scheduled_at', 'arrived_at', 'ended_at', 'outcome', 'cancellation_reason', 'recorded_by', 'offline_reference'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'arrived_at' => 'datetime', 'ended_at' => 'datetime', 'outcome' => 'encrypted', 'cancellation_reason' => 'encrypted'];
    }

    /** @return BelongsTo<Detainee, $this> */
    public function detainee(): BelongsTo
    {
        return $this->belongsTo(Detainee::class);
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /** @return BelongsTo<Visitor, $this> */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
