<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * PushCompletedNotification
 *
 * Envoyée à l'employé ET au supérieur après la fin du push vers DB2.
 * Inclut le rapport de résultat (lignes OK / lignes en erreur).
 */
class PushCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Submission $submission,
        private array $rapport  // ['ok' => int, 'erreurs' => int, 'total' => int]
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hasErrors = $this->rapport['erreurs'] > 0;

        $mail = (new MailMessage)
            ->subject(
                $hasErrors
                    ? "Push DB2 terminé avec erreurs — Dossier #{$this->submission->id}"
                    : "Push DB2 réussi — Dossier #{$this->submission->id}"
            )
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le traitement du dossier #{$this->submission->id} est terminé.")
            ->line("**Résultat du push vers DB2 :**")
            ->line("- Corrections appliquées : **{$this->rapport['ok']}**")
            ->line("- Erreurs : **{$this->rapport['erreurs']}**")
            ->line("- Total traité : **{$this->rapport['total']}**");

        if ($hasErrors) {
            $mail->line('Certaines corrections n\'ont pas pu être appliquées. Consultez le rapport détaillé.');
        }

        return $mail->action('Voir le rapport détaillé', route('employe.submissions.show', $this->submission));
    }

    public function toDatabase(object $notifiable): array
    {
        $hasErrors = $this->rapport['erreurs'] > 0;

        return [
            'type'          => 'push_completed',
            'message'       => $hasErrors
                ? "Push DB2 dossier #{$this->submission->id} : {$this->rapport['ok']} OK, {$this->rapport['erreurs']} erreur(s)."
                : "Push DB2 dossier #{$this->submission->id} : {$this->rapport['ok']} correction(s) appliquée(s) avec succès.",
            'submission_id' => $this->submission->id,
            'rapport'       => $this->rapport,
            'url'           => route('employe.submissions.show', $this->submission),
        ];
    }
}