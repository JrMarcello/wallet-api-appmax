<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'balance',
        'version',
    ];

    protected $casts = [
        'balance' => 'integer',
        'version' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
