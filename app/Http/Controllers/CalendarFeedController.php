<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IcsCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    public function __invoke(Request $request, string $token, IcsCalendar $calendar): Response
    {
        $user = User::query()
            ->where('calendar_token', $token)
            ->where('is_active', true)
            ->first();

        abort_if($user === null, 404);

        $content = $calendar->forUser($user);
        $etag = '"'.hash('sha256', $content).'"';

        $headers = [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="c2me-agenda.ics"',
            'Cache-Control' => 'private, max-age='.max(60, (int) config('crm.calendar.feed_ttl_minutes', 300)),
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
            'ETag' => $etag,
        ];

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response($content, 200, $headers);
    }
}
