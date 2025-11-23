<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class StoredEvent extends Model
{
    use HasUlids;

    public $timestamps = false; // Controlamos 'occurred_at' manualmente no domínio

    protected $fillable = [
        'aggregate_id',
        'event_class',
        'payload',
        'aggregate_version',
        'occurred_at',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array', // Auto-conversão JSON <-> Array
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
