<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $primaryKey = 'profile_id';

    protected $fillable = [
        'user_id',
        'phone_number',
    ];
}
