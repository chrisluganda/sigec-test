<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalConsultation extends Model
{
    /** @use HasFactory<Factory<MedicalConsultation>> */
    use HasFactory;

    protected $fillable = ['detainee_id', 'establishment_id', 'clinician_id', 'offline_reference', 'consulted_at', 'consultation_type', 'reason', 'observations', 'care_provided', 'referral_required', 'referral_notes'];

    protected function casts(): array
    {
        return ['consulted_at' => 'datetime', 'referral_required' => 'boolean', 'reason' => 'encrypted', 'observations' => 'encrypted', 'care_provided' => 'encrypted', 'referral_notes' => 'encrypted'];
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

    /** @return BelongsTo<User, $this> */
    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinician_id');
    }
}
