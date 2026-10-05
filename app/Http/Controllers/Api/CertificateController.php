<?php
// REPLACES the earlier logged-in CertificateController.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Services\CertificateService;
use Illuminate\Support\Facades\Auth;

class CertificateController extends Controller
{
    public function __construct(protected CertificateService $certificates) {}

    public function download()
    {
        $event = Event::where('status', 'active')->first();
        $attendee = $event
            ? Attendee::where('userId', Auth::id())->where('eventId', $event->eventId)->first()
            : null;

        if (!$attendee) {
            return response()->json(['success' => false, 'message' => 'No registration found.'], 404);
        }

        return $this->certificates->download($attendee, $event);
    }
}
