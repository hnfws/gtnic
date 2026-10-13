<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Event;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'event_tenants';

    protected $guarded = ['id'];

    // Relasi Inverse (BelongsTo) ke Event
    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
