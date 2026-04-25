<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modèle User — Utilisateur de l'application
 *
 * Ce modèle étend Authenticatable (gestion de la connexion Laravel)
 * et intègre :
 *   - HasRoles : gestion des rôles et permissions via Spatie
 *   - Notifiable : envoi de notifications mail et in-app
 *   - HasFactory : génération de faux utilisateurs pour les tests
 *
 * Trois rôles existent : admin, superieur, employe
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * Colonnes que l'on autorise à remplir en masse.
     * Toute colonne absente ici ne pourra pas être
     * remplie via User::create([...])
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'created_by',
        'is_active',
    ];

    /**
     * Colonnes cachées dans les réponses JSON.
     * Le mot de passe et le token ne doivent jamais
     * être exposés dans une réponse.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversion automatique des types de colonnes.
     * Laravel convertit automatiquement ces colonnes
     * dans le bon type PHP lors de la lecture.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',   // Hash automatique à l'écriture
            'is_active'         => 'boolean',  // 0/1 en base → true/false en PHP
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /**
     * Un utilisateur peut soumettre plusieurs dossiers (fichiers Excel).
     */
    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * Un supérieur peut avoir effectué plusieurs révisions.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * L'administrateur qui a créé ce compte.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Les comptes créés par cet utilisateur (admin uniquement).
     */
    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }

    /**
     * Les entrées d'audit liées à cet utilisateur.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Scope pour ne récupérer que les comptes actifs.
     * Utilisation : User::active()->get()
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Vérifie si l'utilisateur est administrateur.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Vérifie si l'utilisateur est supérieur.
     */
    public function isSuperieur(): bool
    {
        return $this->hasRole('superieur');
    }

    /**
     * Vérifie si l'utilisateur est employé.
     */
    public function isEmploye(): bool
    {
        return $this->hasRole('employe');
    }

    /**
     * Retourne le libellé lisible du rôle de l'utilisateur.
     * Utilisé dans les vues pour afficher le rôle en français.
     */
    public function getRoleLabel(): string
    {
        if ($this->isAdmin())     return 'Administrateur';
        if ($this->isSuperieur()) return 'Supérieur';
        if ($this->isEmploye())   return 'Employé';
        return 'Inconnu';
    }
}