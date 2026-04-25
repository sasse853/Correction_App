<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * AdminController
 *
 * Accessible uniquement aux utilisateurs ayant le rôle "admin".
 * Gère toutes les opérations liées à la gestion des comptes :
 *   - Tableau de bord admin (statistiques globales)
 *   - Liste des utilisateurs
 *   - Création d'un nouveau compte
 *   - Modification d'un compte existant
 *   - Activation / Désactivation d'un compte
 *   - Attribution / révocation de rôles
 *
 * Toutes les actions sont tracées dans audit_logs.
 */
class AdminController extends Controller
{
    /**
     * Tableau de bord administrateur.
     *
     * Affiche les statistiques globales de l'application :
     * nombre d'utilisateurs, dossiers en cours, logs récents, etc.
     */
    public function dashboard()
    {
        // Statistiques rapides pour les cards du dashboard
        $stats = [
            'total_users'       => User::count(),
            'active_users'      => User::where('is_active', true)->count(),
            'total_submissions' => \App\Models\Submission::count(),
            'pending_reviews'   => \App\Models\Submission::whereIn('statut', ['EN_ATTENTE', 'EN_REVISION'])->count(),
        ];

        // Les 10 dernières actions dans les audit logs
        $recentLogs = AuditLog::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentLogs'));
    }

    /**
     * Liste paginée de tous les utilisateurs.
     * Permet aussi de filtrer par rôle ou par statut (actif/inactif).
     */
    public function users(Request $request)
    {
        $query = User::with('roles')->orderBy('name');

        // Filtre par rôle si renseigné dans l'URL (?role=employe)
        if ($request->filled('role')) {
            $query->role($request->role); // scope Spatie
        }

        // Filtre actif/inactif (?status=active ou ?status=inactive)
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Recherche par nom ou email (?search=xxx)
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $users = $query->paginate(15)->withQueryString();
        $roles  = Role::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Formulaire de création d'un nouvel utilisateur.
     */
    public function createUser()
    {
        // On récupère uniquement les rôles disponibles (pas "admin" pour éviter les erreurs)
        $roles = Role::orderBy('name')->get();

        return view('admin.users.create', compact('roles'));
    }

    /**
     * Enregistre un nouvel utilisateur en base.
     *
     * Règles de validation :
     * - Email unique dans la table users
     * - Mot de passe fort (min 8 car., majuscule, chiffre, symbole)
     * - Le rôle doit exister dans la table roles
     */
    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role'     => ['required', 'string', 'exists:roles,name'],
        ], [
            'email.unique'      => 'Cette adresse email est déjà utilisée.',
            'password.confirmed'=> 'Les mots de passe ne correspondent pas.',
            'role.exists'       => 'Le rôle sélectionné n\'existe pas.',
        ]);

        // Création du compte avec created_by = admin connecté
        $user = User::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'created_by' => auth()->id(),
            'is_active'  => true,
        ]);

        // Attribution du rôle via Spatie Permission
        $user->assignRole($validated['role']);

        // Traçabilité : on enregistre la création dans audit_logs
        AuditLog::record('CREATE_USER', 'OK', [
            'submission_id'    => null,
            'valeur_appliquee' => "Création du compte : {$user->email} (rôle : {$validated['role']})",
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "Compte de {$user->name} créé avec succès.");
    }

    /**
     * Formulaire de modification d'un utilisateur existant.
     */
    public function editUser(User $user)
    {
        $roles = Role::orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Met à jour les informations d'un utilisateur.
     * Le mot de passe n'est modifié que s'il est renseigné dans le formulaire.
     */
    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role'     => ['required', 'string', 'exists:roles,name'],
        ]);

        // Mise à jour des champs de base
        $user->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ]);

        // On ne change le mot de passe que s'il a été renseigné
        if (! empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        // Synchronisation du rôle (remplace l'ancien rôle)
        $user->syncRoles([$validated['role']]);

        // Audit
        AuditLog::record('UPDATE_USER', 'OK', [
            'valeur_appliquee' => "Modification du compte : {$user->email}",
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "Compte de {$user->name} mis à jour.");
    }

    /**
     * Bascule le statut actif/inactif d'un utilisateur.
     *
     * Un admin ne peut pas se désactiver lui-même
     * pour éviter de se bloquer l'accès.
     */
    public function toggleUserStatus(User $user)
    {
        // Sécurité : l'admin ne peut pas désactiver son propre compte
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }

        // Inversion du statut
        $user->update(['is_active' => ! $user->is_active]);

        $action = $user->is_active ? 'réactivé' : 'désactivé';

        // Audit
        AuditLog::record('DEACTIVATE_USER', 'OK', [
            'valeur_appliquee' => "Compte {$action} : {$user->email}",
        ]);

        return back()->with('success', "Compte de {$user->name} {$action} avec succès.");
    }
}
