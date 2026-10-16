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
                            <h1 style="margin:8px 0 0;font-size:22px;color:#ffffff;">Nouvelle demande de contact</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#111827;">
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;width:140px;color:#6b7280;">Type</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;font-weight:bold;">{{ $contact->request_type }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Nom</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">{{ $contact->name }}</td>
                                </tr>
                                @if ($contact->company)
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Société</td>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">{{ $contact->company }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">E-mail</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                        <a href="mailto:{{ $contact->email }}" style="color:#0FA1A1;">{{ $contact->email }}</a>
                                    </td>
                                </tr>
                                @if ($contact->phone)
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Téléphone</td>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->phone) }}" style="color:#0FA1A1;">{{ $contact->phone }}</a>
                                        </td>
                                    </tr>
                                @endif
                            </table>

                            <p style="margin:24px 0 8px;font-size:12px;font-weight:bold;letter-spacing:0.1em;text-transform:uppercase;color:#6b7280;">Message</p>
                            <div style="padding:16px;background:#f9fafb;border-left:3px solid #0FA1A1;border-radius:6px;font-size:14px;line-height:1.6;color:#111827;white-space:pre-wrap;">{{ $contact->message }}</div>

                            <p style="margin:24px 0 0;font-size:12px;color:#9ca3af;">
                                Reçu le {{ $contact->created_at->format('d/m/Y à H:i') }}
                                @if ($contact->referer)
                                    · Source : <a href="{{ $contact->referer }}" style="color:#9ca3af;">{{ \Illuminate\Support\Str::limit($contact->referer, 60) }}</a>
                                @endif
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background:#111827;">
                            <p style="margin:0;font-size:12px;color:#9ca3af;">Ce message provient du formulaire de contact du site c2me.fr</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
