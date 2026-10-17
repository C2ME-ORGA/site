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
                            <h1 style="margin:8px 0 0;font-size:22px;color:#ffffff;">Nouvelle demande de devis</h1>
                            <p style="margin:8px 0 0;font-size:14px;color:#c4ecec;">{{ $quote->kind->getLabel() }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#111827;">
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;width:150px;color:#6b7280;">Nom</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;font-weight:bold;">{{ $quote->name }}</td>
                                </tr>
                                @if ($quote->company)
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Société</td>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">{{ $quote->company }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">E-mail</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                        <a href="mailto:{{ $quote->email }}" style="color:#0FA1A1;">{{ $quote->email }}</a>
                                    </td>
                                </tr>
                                @if ($quote->phone)
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Téléphone</td>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $quote->phone) }}" style="color:#0FA1A1;">{{ $quote->phone }}</a>
                                        </td>
                                    </tr>
                                @endif
                                @if ($quote->city)
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Commune</td>
                                        <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">{{ $quote->city }}</td>
                                    </tr>
                                @endif
                            </table>

                            @if ($summary = $quote->summary())
                                <p style="margin:26px 0 8px;font-size:12px;font-weight:bold;letter-spacing:0.1em;text-transform:uppercase;color:#6b7280;">Détail de la demande</p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#111827;">
                                    @foreach ($summary as $key => $value)
                                        <tr>
                                            <td style="padding:8px 0;border-bottom:1px solid #f3f4f6;width:170px;color:#6b7280;">{{ \Illuminate\Support\Str::headline($key) }}</td>
                                            <td style="padding:8px 0;border-bottom:1px solid #f3f4f6;">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if ($quote->message)
                                <p style="margin:26px 0 8px;font-size:12px;font-weight:bold;letter-spacing:0.1em;text-transform:uppercase;color:#6b7280;">Message</p>
                                <div style="padding:16px;background:#f9fafb;border-left:3px solid #0FA1A1;border-radius:6px;font-size:14px;line-height:1.6;color:#111827;white-space:pre-wrap;">{{ $quote->message }}</div>
                            @endif

                            @if ($quote->files->isNotEmpty())
                                <p style="margin:26px 0 8px;font-size:12px;font-weight:bold;letter-spacing:0.1em;text-transform:uppercase;color:#6b7280;">Plans joints ({{ $quote->files->count() }})</p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#111827;">
                                    @foreach ($quote->files as $file)
                                        <tr>
                                            <td style="padding:8px 0;border-bottom:1px solid #f3f4f6;">{{ $file->original_name }}</td>
                                            <td style="padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:right;color:#6b7280;">{{ $file->humanSize() }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                                <p style="margin:12px 0 0;font-size:12px;color:#9ca3af;">
                                    Les plans sont accessibles depuis le back-office.
                                </p>
                            @endif

                            <p style="margin:26px 0 0;font-size:12px;color:#9ca3af;">
                                Reçue le {{ $quote->created_at->format('d/m/Y à H:i') }}
                                @if ($quote->referer)
                                    · Source : <a href="{{ $quote->referer }}" style="color:#9ca3af;">{{ \Illuminate\Support\Str::limit($quote->referer, 60) }}</a>
                                @endif
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background:#111827;">
                            <p style="margin:0;font-size:12px;color:#9ca3af;">Ce message provient du formulaire de devis du site c2me.fr</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
