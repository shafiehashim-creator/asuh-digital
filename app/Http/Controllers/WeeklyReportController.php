<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Turning the weekly reading report e-mail on or off. */
class WeeklyReportController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['weekly_report' => $request->boolean('weekly_report')])->save();

        return redirect()->route('account')->with('status', 'weekly-report-saved');
    }

    /** One-click stop from the e-mail (signed link, no login needed). */
    public function unsubscribe(User $user): View
    {
        $user->forceFill(['weekly_report' => false])->save();

        return view('weekly-report.unsubscribed');
    }
}
