<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class User extends Model
{
    use HasFactory;
    use Notifiable;
    use HasUuids;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone_number',
        'email', 'auth_id',
    ];

    public function moneyTransitions()
    {
        return $this->hasMany(MoneyTransition::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
