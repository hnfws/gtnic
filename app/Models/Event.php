<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_priority' => 'boolean',
    ];

    // Relasi One-to-Many ke EventTenant
    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }
}
