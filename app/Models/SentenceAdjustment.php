<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SentenceAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'detention_case_id', 'adjustment_type', 'status', 'decision_reference', 'deciding_authority',
        'requested_at', 'decided_at', 'valid_from', 'valid_until', 'conditions',
        'decision_document_path', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'date', 'decided_at' => 'date',
            'valid_from' => 'date', 'valid_until' => 'date', 'conditions' => 'array',
        ];
    }

    public function detentionCase(): BelongsTo
    {
        return $this->belongsTo(DetentionCase::class);
    }
}
