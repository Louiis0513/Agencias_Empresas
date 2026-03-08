<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PanelSuscripcionesConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'slug',
        'description',
        'schedule',
        'cover_image_path',
        'logo_image_path',
        'background_image_path',
        'main_background_color',
        'primary_color',
        'secondary_color',
        'whatsapp_contacts',
        'phone_contacts',
        'locations',
    ];

    protected $casts = [
        'whatsapp_contacts' => 'array',
        'phone_contacts' => 'array',
        'locations' => 'array',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
