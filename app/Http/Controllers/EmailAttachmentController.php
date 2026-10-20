<?php

namespace App\Http\Controllers;

use App\Models\EmailAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailAttachmentController extends Controller
{
    public function download(EmailAttachment $attachment): StreamedResponse
    {
        $message = $attachment->message;

        abort_if($message === null, 404);

        Gate::authorize('view', $message);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->filename);
    }
}
