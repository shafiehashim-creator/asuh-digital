<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'activePass' => $user->activePass(),
            // Includes passes waiting for the launch date (starts_at still null).
            'passes' => $user->passes()->with('plan')->latest()->get(),
            'orders' => $user->orders()->with('plan')->latest()->get(),
        ]);
    }
}
