<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    /** @use HasFactory<Factory<StockMovement>> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['establishment_id', 'offline_reference', 'inventory_item_id', 'movement_type', 'quantity', 'source_location', 'destination_location', 'reference', 'reason', 'related_establishment_id', 'recorded_by', 'created_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'created_at' => 'datetime'];
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

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
