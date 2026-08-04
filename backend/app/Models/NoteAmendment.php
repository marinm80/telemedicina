<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NoteAmendment extends Model
{
    use HasFactory;

    protected $table = 'note_amendments';
    public $timestamps = false; // Solo created_at

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'consultation_note_id',
        'author_id',
        'reason',
        'content',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(ConsultationNote::class, 'consultation_note_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
