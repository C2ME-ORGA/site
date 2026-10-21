<?php

namespace App\Console\Commands;

use App\Enums\FollowUpReminderStage;
use App\Models\User;
use App\Services\FollowUpReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:send-follow-up-reminders
    {--stage= : Étape à traiter (day-before ou due-day)}
    {--dry-run : Simule sans écrire ni envoyer}
    {--user= : Limite le traitement à un utilisateur (ID)}
    {--force : Renvoie aussi les rappels déjà envoyés}
    {--retry : Retente les canaux en échec des derniers jours}')]
#[Description('Envoie les rappels de relance aux propriétaires de comptes et aux administrateurs.')]
class SendFollowUpReminders extends Command
{
    public function handle(FollowUpReminderService $service): int
    {
        $stage = $this->resolveStage();

        if ($stage === null) {
            $this->error('L’option --stage est obligatoire et doit valoir day-before ou due-day.');

            return self::INVALID;
        }

        if (! (bool) config('crm.follow_ups.enabled', true)) {
            $this->warn('Les relances automatiques sont désactivées (CRM_FOLLOW_UPS_ENABLED=false).');

            return self::SUCCESS;
        }

        $userId = null;
        $userOption = $this->option('user');

        if (is_string($userOption) && $userOption !== '') {
            if (! ctype_digit($userOption)) {
                $this->error('L’option --user doit contenir un identifiant numérique.');

                return self::INVALID;
            }

            $userId = (int) $userOption;
            $user = User::query()->find($userId);

            if ($user === null || ! $user->is_active) {
                $this->error('Utilisateur introuvable ou inactif.');

                return self::INVALID;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $retry = (bool) $this->option('retry');

        $result = $service->run(
            stage: $stage,
            dryRun: $dryRun,
            onlyUserId: $userId,
            force: (bool) $this->option('force'),
            retry: $retry,
        );

        if ($dryRun) {
            $this->info('Mode simulation : aucune ligne, notification ou e-mail ne sera créé.');

            if ($result->preview === []) {
                $this->info('Aucune relance à traiter.');

                return self::SUCCESS;
            }

            $this->table(
                ['Compte', 'Destinataire', 'Échéance', 'Étape'],
                array_map(fn (array $row): array => array_values($row), $result->preview),
            );

            $this->info(sprintf(
                '%d compte(s), %d destinataire(s), %d rappel(s) à créer.',
                $result->accounts,
                $result->recipients,
                count($result->preview),
            ));

            return self::SUCCESS;
        }

        if ($retry) {
            $this->info(sprintf(
                'Retraitement %s : %d rappel(s), %d destinataire(s).',
                mb_strtolower($stage->getLabel()),
                $result->processed,
                $result->recipients,
            ));
        } else {
            $this->info(sprintf(
                '%s : %d compte(s), %d destinataire(s), %d rappel(s) créé(s), %d déjà envoyé(s).',
                $stage->getLabel(),
                $result->accounts,
                $result->recipients,
                $result->created,
                $result->alreadySent,
            ));
        }

        if ($result->stale > 0) {
            $this->line(sprintf(
                '%d rappel(s) ignoré(s) : échéance modifiée ou compte supprimé.',
                $result->stale,
            ));
        }

        $this->line(sprintf(
            'Notification in-app : %d envoyée(s), %d échec(s).',
            $result->databaseSent,
            $result->databaseFailed,
        ));

        $this->line(sprintf(
            'E-mail : %d envoyé(s), %d échec(s).',
            $result->mailSent,
            $result->mailFailed,
        ));

        return self::SUCCESS;
    }

    private function resolveStage(): ?FollowUpReminderStage
    {
        $stage = $this->option('stage');

        if (! is_string($stage) || $stage === '') {
            return null;
        }

        return match (str_replace('_', '-', $stage)) {
            'day-before' => FollowUpReminderStage::DayBefore,
            'due-day' => FollowUpReminderStage::DueDay,
            default => null,
        };
    }
}
