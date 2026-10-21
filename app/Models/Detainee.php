<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Detainee extends Model
{
    use HasFactory;

    protected $fillable = ['record_number', 'family_name', 'post_name', 'given_names', 'date_of_birth', 'sex', 'photo_path', 'photo_captured_at', 'photo_recorded_by', 'created_by'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'photo_captured_at' => 'datetime'];
    }

    public function cases(): HasMany
    {
        return $this->hasMany(DetentionCase::class);
    }

    public function stays(): HasMany
    {
        return $this->hasMany(DetentionStay::class);
    }

    public function currentStay(): HasOne
    {
        return $this->hasOne(DetentionStay::class)->whereNull('released_at')->latestOfMany('admitted_at');
    }

    public function cellAssignments(): HasMany
    {
        return $this->hasMany(CellAssignment::class);
    }

    public function biometrics(): HasMany
    {
        return $this->hasMany(DetaineeBiometric::class);
    }

    public function medicalConsultations(): HasMany
    {
        return $this->hasMany(MedicalConsultation::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(PrisonVisit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

