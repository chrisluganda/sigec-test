<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Establishment extends Model
{
    protected $fillable = [
        'code',
        'name',
        'province',
        'city',
        'address',
        'phone',
        'email',
        'status',
        'facility_type',
        'administration_type',
        'security_level',
        'penitentiary_region',
        'court_jurisdiction',
        'authorization_reference',
        'authorization_date',
        'women_dedicated',
        'separate_women_quarters',
        'separate_pretrial_quarters',
    ];

    public function buildings(): HasMany
    {
        return $this->hasMany(EstablishmentBuilding::class);
    }

    protected function casts(): array
    {
        return [
            'authorization_date' => 'date:Y-m-d',
            'women_dedicated' => 'boolean',
            'separate_women_quarters' => 'boolean',
            'separate_pretrial_quarters' => 'boolean',
        ];
    }
}


