<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ConsultationNote extends Model
{
    use HasFactory;

    protected $table = 'consultation_notes';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'consultation_id',
        'symptoms',
        'objective',
        'analysis',
        'plan',
        'status',
        'content_hash',
        'signed_by',
        'signed_at',
        'signed_ip',
        'signed_user_agent',
        'acknowledged_at',
        'pdf_status',
        'pdf_path',
    ];

    protected $casts = [
        'signed_at'       => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(NoteAmendment::class, 'consultation_note_id')->orderBy('created_at', 'asc');
    }
}
