<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * NewSubmissionNotification
 *
 * Envoyée aux supérieurs lorsqu'un employé soumet un nouveau fichier.
 * Canaux : mail (email) + database (notification in-app dans l'interface).
 *
 * La notification in-app est stockée dans la table "notifications" (MySQL)
 * et peut être lue/marquée comme vue depuis l'interface.
 */
class NewSubmissionNotification extends Notification
{
    use Queueable;

    public function __construct(private Submission $submission)
    {
    }

    /**
     * Définit les canaux de notification.
     * 'mail' → envoi d'un email
     * 'database' → stockage dans la table notifications pour l'in-app
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Contenu de l'email envoyé au supérieur.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("📋 Nouveau dossier à réviser — #{$this->submission->id}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Un nouvel dossier vient d'être soumis et attend votre révision.")
            ->line("**Employé :** {$this->submission->user->name}")
            ->line("**Fichier :** {$this->submission->file_original_name}")
            ->line("**Date :** {$this->submission->created_at->format('d/m/Y à H:i')}")
            ->action('Consulter le dossier', route('superieur.submissions.show', $this->submission))
            ->line('Merci de traiter ce dossier dans les meilleurs délais.');
    }

    /**
     * Données stockées dans la table notifications pour l'affichage in-app.
     * Ces données seront lues et affichées dans la cloche de notifications.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'new_submission',
            'message'       => "Nouveau dossier #{$this->submission->id} soumis par {$this->submission->user->name}.",
            'submission_id' => $this->submission->id,
            'url'           => route('superieur.submissions.show', $this->submission),
        ];
    }
}
