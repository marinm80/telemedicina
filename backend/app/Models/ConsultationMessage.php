<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ConsultationMessage extends Model
{
    use HasFactory;

    protected $table = 'consultation_messages';
    public $timestamps = false; // Solo created_at en esquema

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'consultation_id',
        'sender_id',
        'content',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
