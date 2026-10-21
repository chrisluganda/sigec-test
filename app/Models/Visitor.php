<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = ['establishment_id', 'family_name', 'post_name', 'given_names', 'date_of_birth', 'place_of_birth', 'document_type', 'document_reference', 'phone', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean', 'date_of_birth' => 'date']; }
    public function visits(): HasMany { return $this->hasMany(PrisonVisit::class); }
}
