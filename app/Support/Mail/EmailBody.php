<?php

namespace App\Support\Mail;

use App\Models\EmailMessage;

/**
 * Prépare le corps HTML d'un e-mail pour l'affichage ou la réponse.
 */
class EmailBody
{
    /**
     * HTML du message, nettoyé pour être injecté sans risque dans l'interface.
     */
    public static function html(EmailMessage $message): ?string
    {
        $html = trim((string) $message->body_html);

        if ($html === '') {
            return null;
        }

        // Le corps est injecté dans un Shadow DOM : on retire ce qui pourrait
        // exécuter du code ou casser la mise en page de l'administration.
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;
        $html = preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1="#"', $html) ?? $html;

        // Les liens du message s'ouvrent dans un nouvel onglet.
        $html = preg_replace_callback('/<a\b([^>]*)>/i', function (array $matches): string {
            $attributes = $matches[1];

            if (stripos($attributes, 'target=') === false) {
                $attributes .= ' target="_blank"';
            }

            if (stripos($attributes, 'rel=') === false) {
                $attributes .= ' rel="noopener noreferrer"';
            }

            return '<a'.$attributes.'>';
        }, $html) ?? $html;

        return $html;
    }

    /**
     * Version texte du message, sans balises ni CSS.
     */
    public static function text(EmailMessage $message): string
    {
        if (filled($message->body_text)) {
            return (string) $message->body_text;
        }

        $html = preg_replace('#<(style|script|head)\b[^>]*>.*?</\1>#is', ' ', (string) $message->body_html) ?: '';

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Convertit les liens et images relatifs en URLs absolues, afin qu'ils
     * restent accessibles dans la boîte du destinataire.
     */
    public static function absoluteUrls(string $html): string
    {
        return preg_replace_callback(
            '/\b(src|href)\s*=\s*(["\'])(.*?)\2/i',
            function (array $matches): string {
                $url = html_entity_decode(trim($matches[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if ($url === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|\#)~i', $url)) {
                    return $matches[0];
                }

                return $matches[1].'='.$matches[2].url($url).$matches[2];
            },
            $html,
        ) ?? $html;
    }

    /**
     * Décode un en-tête MIME (objet, nom d'expéditeur) encodé en RFC 2047.
     */
    public static function decodeHeader(string $value): string
    {
        if (! function_exists('iconv_mime_decode')) {
            return $value;
        }

        return iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: $value;
    }

    /**
     * Citation du message d'origine, insérée en tête de la réponse.
     */
    public static function replyQuote(EmailMessage $message): string
    {
        $date = $message->received_at?->format('d/m/Y H:i');
        $from = $message->from_name !== null && $message->from_name !== ''
            ? self::decodeHeader($message->from_name).' <'.$message->from_email.'>'
            : $message->from_email;
        $text = self::text($message);

        $header = 'Le '.$date.', '.$from.' a écrit :';

        return '<p><br></p>'
            .'<blockquote style="margin:0;padding-left:12px;border-left:3px solid #d1d5db;color:#6b7280;">'
            .'<p>'.e($header).'</p>'
            .($text !== '' ? '<p>'.nl2br(e($text)).'</p>' : '')
            .'</blockquote>';
    }
}
