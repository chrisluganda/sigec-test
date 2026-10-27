<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetaineeBiometric extends Model
{
    /** @use HasFactory<Factory<DetaineeBiometric>> */
    use HasFactory;

    protected $fillable = ['detainee_id', 'finger_position', 'encrypted_template', 'provider_reference', 'capture_device', 'collection_basis', 'captured_at', 'captured_by', 'status', 'revoked_at'];

    protected function casts(): array
    {
        return ['encrypted_template' => 'encrypted', 'captured_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<Detainee, $this> */
    public function detainee(): BelongsTo
    {
        return $this->belongsTo(Detainee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function capturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
