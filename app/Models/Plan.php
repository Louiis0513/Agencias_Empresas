<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['name', 'slug', 'max_stores', 'max_employees', 'price'];

public function users()
{
    return $this->hasMany(User::class);
}
}
