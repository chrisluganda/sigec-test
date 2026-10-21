<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class DetaineeAdmissionDraft extends Model
{
    protected $fillable = ['offline_reference', 'establishment_id', 'submitted_by', 'encrypted_payload', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function payload(): array
    {
        return json_decode(Crypt::decryptString($this->encrypted_payload), true, flags: JSON_THROW_ON_ERROR);
    }

    public function establishment(): BelongsTo { return $this->belongsTo(Establishment::class); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
