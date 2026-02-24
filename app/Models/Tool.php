<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tool extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'url',
        'image_path',
        'color',
        'is_active',
        'is_public',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_public'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ---- Relations ----
    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    // ---- Helper image ----
    public function imageUrl(): ?string
    {
        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }
        return null;
    }

    // ---- Couleurs disponibles ----
    public static function availableColors(): array
    {
        return [
            'violet' => 'Violet',
            'blue'   => 'Bleu',
            'green'  => 'Vert',
            'orange' => 'Orange',
            'red'    => 'Rouge',
            'pink'   => 'Rose',
            'teal'   => 'Teal',
            'indigo' => 'Indigo',
        ];
    }
}
