<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'category', 'unit', 'description', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function stocks(): HasMany { return $this->hasMany(EstablishmentStock::class); }
    public function movements(): HasMany { return $this->hasMany(StockMovement::class); }
}
