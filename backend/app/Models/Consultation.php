<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Consultation extends Model
{
    use HasFactory;

    protected $table = 'consultations';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'appointment_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConsultationMessage::class, 'consultation_id');
    }
}
