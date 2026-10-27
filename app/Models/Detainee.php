<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $record_number
 * @property string $family_name
 * @property string|null $post_name
 * @property string $given_names
 * @property Carbon|null $date_of_birth
 * @property string $sex
 * @property string|null $photo_path
 * @property Carbon|null $photo_captured_at
 * @property-read Collection<int, DetentionStay> $stays
 */
class Detainee extends Model
{
    /** @use HasFactory<Factory<Detainee>> */
    use HasFactory;

    protected $fillable = ['record_number', 'family_name', 'post_name', 'given_names', 'date_of_birth', 'sex', 'photo_path', 'photo_captured_at', 'photo_recorded_by', 'created_by'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'photo_captured_at' => 'datetime'];
    }

    /** @return HasMany<DetentionCase, $this> */
    public function cases(): HasMany
    {
        return $this->hasMany(DetentionCase::class);
    }

    /** @return HasMany<DetentionStay, $this> */
    public function stays(): HasMany
    {
        return $this->hasMany(DetentionStay::class);
    }

    /** @return HasOne<DetentionStay, $this> */
    public function currentStay(): HasOne
    {
        return $this->hasOne(DetentionStay::class)->whereNull('released_at')->latestOfMany('admitted_at');
    }

    /** @return HasMany<CellAssignment, $this> */
    public function cellAssignments(): HasMany
    {
        return $this->hasMany(CellAssignment::class);
    }

    /** @return HasMany<DetaineeBiometric, $this> */
    public function biometrics(): HasMany
    {
        return $this->hasMany(DetaineeBiometric::class);
    }

    /** @return HasMany<MedicalConsultation, $this> */
    public function medicalConsultations(): HasMany
    {
        return $this->hasMany(MedicalConsultation::class);
    }

    /** @return HasMany<PrisonVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(PrisonVisit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
