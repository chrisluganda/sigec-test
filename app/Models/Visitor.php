<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $establishment_id
 * @property string $family_name
 * @property string|null $post_name
 * @property string $given_names
 * @property Carbon|null $date_of_birth
 * @property string|null $place_of_birth
 * @property string|null $document_type
 * @property string|null $document_reference
 * @property string|null $phone
 * @property bool $is_active
 */
class Visitor extends Model
{
    /** @use HasFactory<Factory<Visitor>> */
    use HasFactory;

    protected $fillable = ['establishment_id', 'family_name', 'post_name', 'given_names', 'date_of_birth', 'place_of_birth', 'document_type', 'document_reference', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'date_of_birth' => 'date'];
    }

    /** @return HasMany<PrisonVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(PrisonVisit::class);
    }
}
