<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property int|null $establishment_id
 * @property string $event
 * @property string|null $subject_type
 * @property int|string|null $subject_id
 * @property string $summary
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property-read User|null $actor
 * @property-read Establishment|null $establishment
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_id', 'establishment_id', 'event', 'subject_type', 'subject_id', 'summary', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit entries are append-only.'));
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}
