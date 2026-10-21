<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalConsultation extends Model
{
    use HasFactory;

    protected $fillable = ['detainee_id', 'establishment_id', 'clinician_id', 'offline_reference', 'consulted_at', 'consultation_type', 'reason', 'observations', 'care_provided', 'referral_required', 'referral_notes'];
    protected function casts(): array { return ['consulted_at' => 'datetime', 'referral_required' => 'boolean', 'reason' => 'encrypted', 'observations' => 'encrypted', 'care_provided' => 'encrypted', 'referral_notes' => 'encrypted']; }
    public function detainee(): BelongsTo { return $this->belongsTo(Detainee::class); }
    public function establishment(): BelongsTo { return $this->belongsTo(Establishment::class); }
    public function clinician(): BelongsTo { return $this->belongsTo(User::class, 'clinician_id'); }
}
