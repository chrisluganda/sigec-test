<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetentionCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'detainee_id', 'case_reference', 'case_type', 'court_name', 'court_level',
        'judgment_final_at', 'case_status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['judgment_final_at' => 'date'];
    }

    public function detainee(): BelongsTo
    {
        return $this->belongsTo(Detainee::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DetentionOrder::class);
    }

    public function stays(): HasMany
    {
        return $this->hasMany(DetentionStay::class);
    }

    public function sentenceAdjustments(): HasMany
    {
        return $this->hasMany(SentenceAdjustment::class);
    }
}
