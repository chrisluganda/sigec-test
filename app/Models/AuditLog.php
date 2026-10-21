<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

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

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function establishment(): BelongsTo { return $this->belongsTo(Establishment::class); }
}
