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
class DetentionOrder extends Model
{
    /** @use HasFactory<Factory<DetentionOrder>> */
    use HasFactory;

    protected $fillable = [
        'detention_case_id', 'magistrate_id', 'order_type', 'reference', 'issuing_authority', 'issued_at',
        'effective_at', 'expires_at', 'status', 'document_path', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date', 'effective_at' => 'date', 'expires_at' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DetentionCase, $this> */
    /** @return BelongsTo<DetentionCase, $this> */
    public function detentionCase(): BelongsTo
    {
        return $this->belongsTo(DetentionCase::class);
    }

    /** @return HasMany<DetentionStay, $this> */
    /** @return HasMany<DetentionStay, $this> */
    public function stays(): HasMany
    {
        return $this->hasMany(DetentionStay::class);
    }

    /** @return BelongsTo<Magistrate, $this> */
    /** @return BelongsTo<Magistrate, $this> */
    public function magistrate(): BelongsTo
    {
        return $this->belongsTo(Magistrate::class);
    }

    /** @return BelongsTo<User, $this> */
    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
