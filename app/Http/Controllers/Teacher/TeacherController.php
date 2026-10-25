<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Invitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The institution administrator invites and removes teachers. */
class TeacherController extends Controller
{
    public function store(Request $request, Invitations $invitations): RedirectResponse
    {
        abort_unless($request->user()->isInstitutionAdmin(), 403);

        $data = $request->validateWithBag('invite', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
        ], ['email.unique' => 'E-mel ini sudah mempunyai akaun ASUH Digital.'], ['name' => 'nama', 'email' => 'e-mel']);

        $invitations->invite($request->user()->institution, $data['email'], $data['name'], Role::Teacher, $request->user());

        return redirect()->route('teacher.home')->with('status', 'teacher-invited');
    }

    /**
     * The teacher's account stays (as a plain account) but leaves the
     * institution; their classes pass to the administrator.
     */
    public function destroy(Request $request, User $teacher): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin->isInstitutionAdmin() && $teacher->institution_id === $admin->institution_id && $teacher->id !== $admin->id, 404);

        DB::transaction(function () use ($admin, $teacher) {
            $admin->institution->classrooms()->where('teacher_id', $teacher->id)->update(['teacher_id' => $admin->id]);
            $teacher->forceFill(['role' => Role::Parent, 'institution_id' => null])->save();
        });

        return redirect()->route('teacher.home')->with('status', 'teacher-removed');
    }
}
