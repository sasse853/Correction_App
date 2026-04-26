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
        // Nouvelles colonnes de révision ligne par ligne
        'statut_revision',
        'commentaire_sup',
        'valeur_corrigee',
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

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForVersion($query, int $version)
    {
        return $query->where('version', $version);
    }

    public function scopePending($query)
    {
        return $query->where('push_statut', 'PENDING');
    }

    /**
     * Scope : lignes refusées par le supérieur (l'employé doit corriger).
     */
    public function scopeRefused($query)
    {
        return $query->where('statut_revision', 'REFUSE');
    }

    /**
     * Scope : lignes validées par le supérieur.
     */
    public function scopeValidated($query)
    {
        return $query->where('statut_revision', 'VALIDE');
    }

    /**
     * Scope : lignes pas encore révisées.
     */
    public function scopeNotReviewed($query)
    {
        return $query->where('statut_revision', 'PENDING');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Retourne la valeur effective à pousser vers DB2.
     *
     * PRIORITÉ : valeur_corrigee (saisie par l'employé après correction)
     * sur valeur_correction (valeur originale du fichier Excel).
     *
     * C'est cette méthode que PushDb2Service doit appeler
     * pour savoir quelle valeur envoyer à DB2.
     */
    public function getValeurEffective(): string
    {
        return $this->valeur_corrigee ?? $this->valeur_correction;
    }

    /**
     * Vérifie si la ligne a été validée par le supérieur.
     */
    public function isValidated(): bool
    {
        return $this->statut_revision === 'VALIDE';
    }

    /**
     * Vérifie si la ligne a été refusée par le supérieur.
     */
    public function isRefused(): bool
    {
        return $this->statut_revision === 'REFUSE';
    }

    /**
     * Vérifie si la ligne a été corrigée par l'employé
     * (valeur_corrigee renseignée après un refus).
     */
    public function isCorrectedByEmployee(): bool
    {
        return ! is_null($this->valeur_corrigee);
    }

    /**
     * Retourne la classe CSS du badge selon statut_revision.
     * Utilisée dans les vues pour coloriser les lignes.
     */
    public function getRevisionBadgeClass(): string
    {
        return match($this->statut_revision) {
            'VALIDE'  => 'badge-emerald',
            'REFUSE'  => 'badge-red',
            default   => 'badge-gray',  // PENDING
        };
    }

    /**
     * Retourne le label lisible du statut de révision.
     */
    public function getRevisionLabel(): string
    {
        return match($this->statut_revision) {
            'VALIDE'  => 'Validé',
            'REFUSE'  => 'Refusé',
            default   => 'En attente',
        };
    }
}