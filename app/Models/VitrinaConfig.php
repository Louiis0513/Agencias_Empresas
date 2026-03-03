<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VitrinaConfig extends Model
{
    use HasFactory;

    protected $fillable = [
			'store_id',
			'slug',
			'cover_image_path',
			'logo_image_path',
			'background_image_path',
			'description',
			'schedule',
			'show_products',
			'show_plans',
			'default_page_size',
			'default_min_price',
			'default_max_price',
			'whatsapp_contacts',
			'phone_contacts',
			'locations',
		];

    protected $casts = [
        'show_products' => 'boolean',
        'show_plans' => 'boolean',
			'default_page_size' => 'integer',
			'default_min_price' => 'integer',
			'default_max_price' => 'integer',
        'whatsapp_contacts' => 'array',
        'phone_contacts' => 'array',
        'locations' => 'array',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
