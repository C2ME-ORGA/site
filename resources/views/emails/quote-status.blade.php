<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#0FA1A1;padding:24px 32px;">
                            <p style="margin:0;font-size:13px;font-weight:bold;letter-spacing:0.12em;text-transform:uppercase;color:#BDE950;">C2ME</p>
                            <h1 style="margin:8px 0 0;font-size:22px;color:#ffffff;">Statut du devis mis à jour</h1>
                            <p style="margin:8px 0 0;font-size:14px;color:#c4ecec;">{{ $toStatus->getLabel() }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#111827;">
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;width:150px;color:#6b7280;">Référence</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;font-weight:bold;">{{ $quote->reference }}</td>
                                </tr>
                                @if ($quote->subject)
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Objet</td>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">{{ $quote->subject }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Statut précédent</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">{{ $fromStatus->getLabel() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Nouveau statut</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;font-weight:bold;">{{ $toStatus->getLabel() }}</td>
                                </tr>
                            </table>

                            <p style="margin:26px 0 0;font-size:14px;color:#111827;">
                                Ce devis a changé de statut le {{ now()->format('d/m/Y à H:i') }}.
                            </p>

                            <p style="margin:26px 0 0;font-size:12px;color:#9ca3af;">
                                Connectez-vous au back-office C2ME pour consulter le devis.
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
