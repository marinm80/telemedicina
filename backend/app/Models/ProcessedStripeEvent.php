<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ProcessedStripeEvent extends Model
{
    use HasFactory;

    protected $table = 'processed_stripe_events';
    protected $primaryKey = 'event_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
