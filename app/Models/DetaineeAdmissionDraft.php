<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $establishment_id
 * @property int|null $submitted_by
 * @property string $encrypted_payload
 * @property string $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon|null $created_at
 * @property-read Establishment $establishment
 * @property-read User|null $submitter
 * @property-read User|null $reviewer
 */
class DetaineeAdmissionDraft extends Model
{
    protected $fillable = ['offline_reference', 'establishment_id', 'submitted_by', 'encrypted_payload', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return json_decode(Crypt::decryptString($this->encrypted_payload), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
