<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StagingCorrection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'version',
        'ligne_ref',
        'table_db2',
        'champ',
        'valeur_correction',
        'cle_primaire',
        'appliquee',
        'push_statut',
        'push_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'appliquee'  => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function scopeForVersion($query, int $version)
    {
        return $query->where('version', $version);
    }

    public function scopePending($query)
    {
        return $query->where('push_statut', 'PENDING');
    }
}
