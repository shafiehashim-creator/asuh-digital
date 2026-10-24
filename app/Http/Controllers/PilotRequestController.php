<?php

namespace App\Http\Controllers;

use App\Enums\PilotStatus;
use App\Http\Requests\StorePilotRequest;
use App\Mail\PilotRequestConfirmation;
use App\Mail\PilotRequestReceived;
use App\Models\PilotRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PilotRequestController extends Controller
{
    public function create(): View
    {
        return view('pilot.create', ['pilotDays' => (int) Setting::get('pilot_duration_days')]);
    }

    public function store(StorePilotRequest $request): RedirectResponse
    {
        $pilot = PilotRequest::query()->create(
            $request->safe()->except(['pdpa_consent', 'website']) + ['status' => PilotStatus::New],
        );

        Mail::to($pilot->email)->send(new PilotRequestConfirmation($pilot));

        $adminEmails = array_filter((array) Setting::get('admin_notification_emails'));

        if ($adminEmails) {
            Mail::to($adminEmails)->send(new PilotRequestReceived($pilot));
        }

        return redirect()->route('pilot.thanks')->with('pilot_institution', $pilot->institution_name);
    }

    public function thanks(): View
    {
        return view('pilot.thanks', ['pilotDays' => (int) Setting::get('pilot_duration_days')]);
    }
}
