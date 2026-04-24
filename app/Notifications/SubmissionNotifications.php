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
 * et demande des corrections. Le commentaire du supérieur
 * est inclus dans la notification pour que l'employé sache
 * exactement quoi corriger.
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
            ->subject("⚠️ Correction demandée — Dossier #{$this->submission->id}")
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
            ->subject("✅ Dossier approuvé — #{$this->submission->id}")
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


/**
 * PushCompletedNotification
 *
 * Envoyée à l'employé ET au supérieur après la fin du push vers DB2.
 * Inclut le rapport de résultat (lignes OK / lignes en erreur).
 * Envoyée que le push soit un succès total ou partiel.
 */
class PushCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Submission $submission,
        private array $rapport  // ['ok' => int, 'erreurs' => int, 'total' => int, 'statut' => string]
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // On adapte le message selon qu'il y a eu des erreurs ou non
        $hasErrors = $this->rapport['erreurs'] > 0;

        $mail = (new MailMessage)
            ->subject(
                $hasErrors
                    ? "⚠️ Push DB2 terminé avec erreurs — Dossier #{$this->submission->id}"
                    : "✅ Push DB2 réussi — Dossier #{$this->submission->id}"
            )
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le traitement du dossier #{$this->submission->id} est terminé.")
            ->line("**Résultat du push vers DB2 :**")
            ->line("- ✅ Corrections appliquées : **{$this->rapport['ok']}**")
            ->line("- ❌ Erreurs : **{$this->rapport['erreurs']}**")
            ->line("- 📊 Total traité : **{$this->rapport['total']}**");

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
