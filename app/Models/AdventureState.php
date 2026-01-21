<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdventureState extends Model
{
    protected $fillable = ['session_id', 'adventure'];
    protected $casts = ['adventure' => 'array'];
}
