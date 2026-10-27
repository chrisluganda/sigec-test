<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 */
class DetentionCase extends Model
{
    /** @use HasFactory<Factory<DetentionCase>> */
    use HasFactory;

    protected $fillable = [
        'detainee_id', 'case_reference', 'case_type', 'court_name', 'court_level',
        'judgment_final_at', 'case_status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['judgment_final_at' => 'date'];
    }

    /** @return BelongsTo<Detainee, $this> */
    /** @return BelongsTo<Detainee, $this> */
    public function detainee(): BelongsTo
    {
        return $this->belongsTo(Detainee::class);
    }

    /** @return HasMany<DetentionOrder, $this> */
    /** @return HasMany<DetentionOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(DetentionOrder::class);
    }

    /** @return HasMany<DetentionStay, $this> */
    /** @return HasMany<DetentionStay, $this> */
    public function stays(): HasMany
    {
        return $this->hasMany(DetentionStay::class);
    }

    /** @return HasMany<SentenceAdjustment, $this> */
    /** @return HasMany<SentenceAdjustment, $this> */
    public function sentenceAdjustments(): HasMany
    {
        return $this->hasMany(SentenceAdjustment::class);
    }
}
