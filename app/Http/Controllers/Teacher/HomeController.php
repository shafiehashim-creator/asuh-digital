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

        $user = $request->user();

        return view('teacher.home', [
            'user' => $user,
            'institution' => $institution,
            'license' => $institution->activeLicense(),
            'pupilCount' => $institution->pupilCount(),
            'classrooms' => $institution->classrooms()
                ->when(! $user->isInstitutionAdmin(), fn ($q) => $q->where('teacher_id', $user->id))
                ->withCount('pupils')->with('teacher')->get(),
            'teachers' => $user->isInstitutionAdmin() ? $institution->users()->orderBy('name')->get() : collect(),
            'invitations' => $user->isInstitutionAdmin()
                ? $institution->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->get()
                : collect(),
        ]);
    }
}
