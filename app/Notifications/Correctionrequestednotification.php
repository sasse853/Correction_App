<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * CorrectionRequestedNotification
 *
 * Envoyée à l'employé quand le supérieur rejette son dossier
 * et demande des corrections.
 */
class CorrectionRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Submission $submission,
        private string $commentaire
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Correction demandée — Dossier #{$this->submission->id}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre dossier a été examiné et nécessite des corrections avant d'être approuvé.")
            ->line("**Commentaires du supérieur :**")
            ->line($this->commentaire)
            ->action('Voir le dossier et re-soumettre', route('employe.submissions.show', $this->submission))
            ->line('Merci de prendre en compte ces remarques et de re-soumettre votre fichier corrigé.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'correction_requested',
            'message'       => "Votre dossier #{$this->submission->id} nécessite des corrections.",
            'submission_id' => $this->submission->id,
            'commentaire'   => $this->commentaire,
            'url'           => route('employe.submissions.show', $this->submission),
        ];
    }
}