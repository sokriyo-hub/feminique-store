<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlockedPhone extends Model
{
    protected $fillable = ['phone', 'reason'];

    // Every order ever placed with this mobile number (newest first)
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'phone', 'phone')->latest()->orderByDesc('id');
    }
}