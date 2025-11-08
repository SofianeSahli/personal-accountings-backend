<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MoneyTransition extends Model
{
    use HasUuids;

    protected $fillable = [
        'amount',
        'description',
        'categorie_id',
        'user_id',
    ];

    public function categorie()
    {
        return $this->belongsTo(categorie::class, 'categorie_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
