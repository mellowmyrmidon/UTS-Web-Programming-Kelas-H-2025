<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameState extends Model
{
    protected $fillable = ['session_id', 'state'];
    protected $casts = ['state' => 'array'];
}
