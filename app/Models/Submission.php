<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Submission extends Model
{
    protected $fillable = [
        'user_id',
        'uuid',
        'file_path',
        'file_original_name',
        'version',
        'statut',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (Submission $submission) {
            $submission->uuid ??= (string) Str::uuid();
        });
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function corrections()
    {
        return $this->hasMany(StagingCorrection::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function latestReview()
    {
        return $this->hasOne(Review::class)->latestOfMany();
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeByStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    public function scopePendingReview($query)
    {
        return $query->whereIn('statut', ['EN_ATTENTE', 'EN_REVISION']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function getStatutBadgeClass(): string
    {
        return match ($this->statut) {
            'EN_ATTENTE'            => 'bg-yellow-100 text-yellow-800',
            'EN_REVISION'           => 'bg-blue-100 text-blue-800',
            'EN_CORRECTION'         => 'bg-orange-100 text-orange-800',
            'APPROUVE'              => 'bg-green-100 text-green-800',
            'TERMINE'               => 'bg-emerald-100 text-emerald-800',
            'TERMINE_AVEC_ERREURS'  => 'bg-red-100 text-red-800',
            default                 => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStatutLabel(): string
    {
        return match ($this->statut) {
            'EN_ATTENTE'            => 'En attente',
            'EN_REVISION'           => 'En révision',
            'EN_CORRECTION'         => 'En correction',
            'APPROUVE'              => 'Approuvé',
            'TERMINE'               => 'Terminé',
            'TERMINE_AVEC_ERREURS'  => 'Terminé avec erreurs',
            default                 => $this->statut,
        };
    }

    public function getTotalCorrections(): int
    {
        return $this->corrections()->where('version', $this->version)->count();
    }

    public function getAppliedCorrections(): int
    {
        return $this->corrections()->where('version', $this->version)->where('appliquee', true)->count();
    }
}
