<?php
/**
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 */
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
