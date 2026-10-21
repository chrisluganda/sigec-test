<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Magistrate extends Model
{
    use HasFactory;

    protected $fillable = ['official_reference', 'family_name', 'post_name', 'given_names', 'function', 'court_name', 'court_level', 'jurisdiction', 'province', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function detentionOrders(): HasMany { return $this->hasMany(DetentionOrder::class); }
}
