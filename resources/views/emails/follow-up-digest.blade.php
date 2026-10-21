<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:760px;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#0FA1A1;padding:24px 32px;">
                            <p style="margin:0;font-size:13px;font-weight:bold;letter-spacing:0.12em;text-transform:uppercase;color:#BDE950;">C2ME</p>
                            <h1 style="margin:8px 0 0;font-size:22px;color:#ffffff;">{{ $stage->notificationTitle($reminders->count()) }}</h1>
                            <p style="margin:8px 0 0;font-size:14px;color:#c4ecec;">Récapitulatif des relances à traiter</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 18px;font-size:14px;color:#111827;">Bonjour {{ $user->name }},</p>
                            <p style="margin:0 0 24px;font-size:14px;color:#4b5563;">
                                Voici les relances qui vous concernent. Vous pouvez les traiter directement depuis la page dédiée du back-office.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:12px;color:#111827;">
                                <thead>
                                    <tr style="background:#f9fafb;">
                                        <th align="left" style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">Compte</th>
                                        <th align="left" style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">Carnet</th>
                                        <th align="left" style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">Statut</th>
                                        <th align="left" style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">Propriétaire</th>
                                        <th align="left" style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">Échéance</th>
                                        <th align="left" style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">Contact</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reminders as $reminder)
                                        @php
                                            $account = $reminder->account;
                                            $contact = $account?->primaryContact();
                                            $isOverdue = $reminder->due_at->lt(now()->startOfDay());
                                        @endphp
                                        @if ($account)
                                            <tr>
                                                <td style="padding:10px 8px;border-bottom:1px solid #f3f4f6;vertical-align:top;">
                                                    <a href="{{ \App\Support\FilamentAccountUrl::view($account) }}" style="color:#0e8577;font-weight:bold;text-decoration:none;">{{ $account->name }}</a>
                                                    @if ($account->city)
                                                        <br><span style="color:#6b7280;">{{ $account->city }}</span>
                                                    @endif
                                                </td>
                                                <td style="padding:10px 8px;border-bottom:1px solid #f3f4f6;vertical-align:top;">{{ $account->book->shortLabel() }}</td>
                                                <td style="padding:10px 8px;border-bottom:1px solid #f3f4f6;vertical-align:top;">{{ $account->status->getLabel() }}</td>
                                                <td style="padding:10px 8px;border-bottom:1px solid #f3f4f6;vertical-align:top;">{{ $account->owner?->name ?? '—' }}</td>
                                                <td style="padding:10px 8px;border-bottom:1px solid #f3f4f6;vertical-align:top;{{ $isOverdue ? 'color:#b91c1c;font-weight:bold;' : '' }}">
                                                    {{ $reminder->due_at->format('d/m/Y à H:i') }}
                                                    @if ($isOverdue)
                                                        <br><span style="font-size:11px;">En retard</span>
                                                    @endif
                                                </td>
                                                <td style="padding:10px 8px;border-bottom:1px solid #f3f4f6;vertical-align:top;">
                                                    @if ($contact)
                                                        {{ $contact->fullName() }}
                                                        @if ($contact->phone)
                                                            <br><a href="tel:{{ $contact->phone }}" style="color:#0e8577;text-decoration:none;">{{ $contact->phone }}</a>
                                                        @endif
                                                    @elseif ($account->phone)
                                                        <a href="tel:{{ $account->phone }}" style="color:#0e8577;text-decoration:none;">{{ $account->phone }}</a>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>

                            <p style="margin:28px 0 0;">
                                <a href="{{ $followUpsUrl }}" style="display:inline-block;background:#0e8577;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:12px 20px;border-radius:8px;">
                                    Ouvrir mes relances
                                </a>
                            </p>

                            <p style="margin:26px 0 0;font-size:12px;color:#9ca3af;">
                                Cet e-mail est un récapitulatif automatique. Le détail et les actions sont disponibles dans le back-office C2ME.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background:#111827;">
                            <p style="margin:0;font-size:12px;color:#9ca3af;">Notification automatique — back-office C2ME</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
