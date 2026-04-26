<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SubmissionApprovedNotification
 *
 * Envoyée à l'employé dès que le supérieur approuve son dossier,
 * juste avant le déclenchement du push vers DB2.
 */
class SubmissionApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(private Submission $submission)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Dossier approuvé — #{$this->submission->id}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre dossier a été approuvé. Les corrections sont en cours d'application sur DB2.")
            ->action('Voir le dossier', route('employe.submissions.show', $this->submission));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'submission_approved',
            'message'       => "Votre dossier #{$this->submission->id} a été approuvé. Push DB2 en cours...",
            'submission_id' => $this->submission->id,
            'url'           => route('employe.submissions.show', $this->submission),
        ];
    }
}