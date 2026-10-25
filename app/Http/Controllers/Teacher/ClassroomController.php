<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\AgeGroup;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Services\ClassReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function create(): View
    {
        return view('teacher.classroom-form', ['classroom' => new Classroom]);
    }

    public function store(Request $request): RedirectResponse
    {
        $classroom = $request->user()->institution->classrooms()->create(
            $this->validated($request) + ['teacher_id' => $request->user()->id],
        );

        return redirect()->route('teacher.classrooms.show', $classroom)->with('status', 'classroom-created');
    }

    public function show(Request $request, Classroom $classroom, ClassReport $report): View
    {
        $this->authorizeClassroom($request, $classroom);
        $institution = $request->user()->institution;
        $license = $institution->activeLicense();

        return view('teacher.classroom', [
            'classroom' => $classroom->load('pupils', 'teacher'),
            'license' => $license,
            'seatsLeft' => $license ? max(0, $license->max_students - $institution->pupilCount()) : 0,
            'report' => $report->forClassroom($classroom),
        ]);
    }

    public function edit(Request $request, Classroom $classroom): View
    {
        $this->authorizeClassroom($request, $classroom);

        return view('teacher.classroom-form', ['classroom' => $classroom]);
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);
        $classroom->update($this->validated($request));

        return redirect()->route('teacher.classrooms.show', $classroom)->with('status', 'classroom-saved');
    }

    /** Removes the class with its pupils and everything they read (cascade). */
    public function destroy(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);
        $classroom->delete();

        return redirect()->route('teacher.home')->with('status', 'classroom-deleted');
    }

    /** A fresh code, for when the old one has spread beyond the class. */
    public function newCode(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeClassroom($request, $classroom);
        $classroom->update(['code' => Classroom::newCode()]);

        return redirect()->route('teacher.classrooms.show', $classroom)->with('status', 'code-changed');
    }

    /** @return array{name: string, age_group: string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'age_group' => ['required', Rule::in([AgeGroup::SixToNine->value, AgeGroup::TenToThirteen->value])],
        ], [], ['name' => 'nama kelas', 'age_group' => 'kumpulan umur']);
    }

    public static function authorizeClassroom(Request $request, Classroom $classroom): void
    {
        abort_unless($request->user()->canManageClassroom($classroom), 404);
    }
}
