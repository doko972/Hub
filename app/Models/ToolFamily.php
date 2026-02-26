<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ToolFamily extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'color',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ---- Relations ----
    public function tools()
    {
        return $this->hasMany(Tool::class);
    }

    // ---- Couleurs disponibles (même palette que Tool) ----
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
