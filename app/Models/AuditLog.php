<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role',
        'action',
        'submission_id',
        'table_db2',
        'champ_modifie',
        'valeur_appliquee',
        'statut',
        'message_erreur',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    // ── Static helper to record any action ───────────────────────────────────

    public static function record(
        string $action,
        string $statut = 'OK',
        array  $extra  = []
    ): self {
        /** @var User|null $user */
        $user = Auth::user();

        return static::create(array_merge([
            'user_id'    => $user?->id,
            'role'       => $user?->getRoleNames()->first(),
            'action'     => $action,
            'statut'     => $statut,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ], $extra));
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            'UPLOAD'          => 'Upload Excel',
            'APPROBATION'     => 'Approbation',
            'REJET'           => 'Rejet',
            'CORRECTION'      => 'Correction demandée',
            'PUSH_DB2'        => 'Push vers DB2',
            'LOGIN'           => 'Connexion',
            'LOGOUT'          => 'Déconnexion',
            'CREATE_USER'     => 'Création utilisateur',
            'UPDATE_USER'     => 'Modification utilisateur',
            'DEACTIVATE_USER' => 'Désactivation utilisateur',
            default           => $this->action,
        };
    }
}
