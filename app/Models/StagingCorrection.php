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
        // Colonnes de révision
        'statut_revision',
        'commentaire_sup',
        // Colonnes corrigées par l'employé (toutes les colonnes modifiables)
        'valeur_corrigee',
        'table_db2_corrigee',
        'cle_primaire_corrigee',
        'champ_corrige',
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

    public function scopeRefused($query)
    {
        return $query->where('statut_revision', 'REFUSE');
    }

    public function scopeValidated($query)
    {
        return $query->where('statut_revision', 'VALIDE');
    }

    public function scopeNotReviewed($query)
    {
        return $query->where('statut_revision', 'PENDING');
    }

    // ── Helpers valeurs effectives ────────────────────────────────────────────

    /**
     * Retourne la valeur effective à pousser vers DB2.
     * Priorité : valeur corrigée par l'employé > valeur originale Excel.
     */
    public function getValeurEffective(): string
    {
        return $this->valeur_corrigee ?? $this->valeur_correction;
    }

    /**
     * Retourne la table DB2 effective.
     * Priorité : table corrigée par l'employé > table originale Excel.
     */
    public function getTableDb2Effective(): string
    {
        return $this->table_db2_corrigee ?? $this->table_db2;
    }

    /**
     * Retourne la clé primaire effective.
     * Priorité : clé corrigée par l'employé > clé originale Excel.
     */
    public function getCleprimaireEffective(): ?string
    {
        return $this->cle_primaire_corrigee ?? $this->cle_primaire;
    }

    /**
     * Retourne le champ effectif à modifier.
     * Priorité : champ corrigé par l'employé > champ original Excel.
     */
    public function getChampEffective(): string
    {
        return $this->champ_corrige ?? $this->champ;
    }

    /**
     * Vérifie si au moins une colonne a été corrigée par l'employé.
     * Utilisé pour savoir si la ligne a été traitée.
     */
    public function isCorrectedByEmployee(): bool
    {
        return ! is_null($this->valeur_corrigee)
            || ! is_null($this->table_db2_corrigee)
            || ! is_null($this->cle_primaire_corrigee)
            || ! is_null($this->champ_corrige);
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
     * Retourne la classe CSS du badge selon statut_revision.
     */
    public function getRevisionBadgeClass(): string
    {
        return match($this->statut_revision) {
            'VALIDE'  => 'badge-emerald',
            'REFUSE'  => 'badge-red',
            default   => 'badge-gray',
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