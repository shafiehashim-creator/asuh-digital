<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $institution = $request->user()->institution;

        return view('teacher.home', [
            'user' => $request->user(),
            'institution' => $institution,
            'license' => $institution->activeLicense(),
        ]);
    }
}
