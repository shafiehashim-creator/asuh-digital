<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ChildAvatar;
use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pupils are added by nickname, many at once (one per line), within the
 * licence's pupil limit. Nicknames are unique in a class so each child can
 * pick their own name.
 */
class PupilController extends Controller
{
    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        ClassroomController::authorizeClassroom($request, $classroom);

        $request->validate(['names' => ['required', 'string', 'max:3000']], [], ['names' => 'nama murid']);

        $taken = $classroom->pupils()->pluck('nickname')->map(fn ($name) => Str::lower($name))->all();
        $names = collect(preg_split('/\R/', $request->string('names')))
            ->map(fn ($name) => Str::limit(trim(preg_replace('/\s+/', ' ', $name)), 30, ''))
            ->filter()
            ->unique(fn ($name) => Str::lower($name))
            ->reject(fn ($name) => in_array(Str::lower($name), $taken, true))
            ->values();

        if ($names->isEmpty()) {
            return back()->withErrors(['names' => 'Tiada nama baharu untuk ditambah (nama yang sama sudah ada dalam kelas ini).']);
        }

        $institution = $classroom->institution;
        $license = $institution->activeLicense();
        $seatsLeft = $license ? $license->max_students - $institution->pupilCount() : 0;

        if ($names->count() > $seatsLeft) {
            return back()->withInput()->withErrors(['names' => $license
                ? "Lesen membenarkan {$license->max_students} murid. Baki tempat: {$seatsLeft}. Kurangkan nama atau hubungi ASUH Digital untuk menambah murid."
                : 'Tiada lesen aktif. Hubungi ASUH Digital.']);
        }

        $avatars = ChildAvatar::cases();
        $offset = $classroom->pupils()->count();

        foreach ($names as $i => $name) {
            $pupil = new Child([
                'nickname' => $name,
                'age_group' => $classroom->age_group,
                'avatar' => $avatars[($offset + $i) % count($avatars)],
            ]);
            $pupil->classroom_id = $classroom->id;
            $pupil->save();
        }

        return redirect()->route('teacher.classrooms.show', $classroom)
            ->with('status', 'pupils-added')->with('added', $names->count());
    }

    /** PDPA: the pupil's reading records, quiz attempts and badges go too. */
    public function destroy(Request $request, Classroom $classroom, Child $pupil): RedirectResponse
    {
        ClassroomController::authorizeClassroom($request, $classroom);
        abort_unless($pupil->classroom_id === $classroom->id, 404);

        $pupil->delete();

        return redirect()->route('teacher.classrooms.show', $classroom)->with('status', 'pupil-deleted');
    }
}
