<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstablishmentStock extends Model
{
    /** @use HasFactory<Factory<EstablishmentStock>> */
    use HasFactory;

    protected $fillable = ['establishment_id', 'inventory_item_id', 'location', 'quantity', 'reorder_threshold'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'reorder_threshold' => 'decimal:3'];
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
