<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PreConsultationForm extends Model
{
    use HasFactory;

    protected $table = 'pre_consultation_forms';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'appointment_id',
        'motivo',
        'sintomas',
        'form_data',
    ];

    protected $casts = [
        'form_data' => 'array',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}
