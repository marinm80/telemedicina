<?php
/**
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 */
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'consultation_id',
        'referring_doctor_id',
        'patient_id',
        'specialty_id',
        'specialty_name',
        'referred_doctor_id',
        'reason',
        'priority',
        'status',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function referringDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referring_doctor_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function referredDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_doctor_id');
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }
}
