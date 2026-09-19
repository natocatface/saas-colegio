<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        $schools = School::query()
            ->withCount(['users', 'students'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('schools.index', compact('schools'));
    }

    public function show(School $school): View
    {
        $stats = [
            'students' => Student::where('school_id', $school->id)->count(),
            'teachers' => Teacher::where('school_id', $school->id)->count(),
            'users' => User::where('school_id', $school->id)->count(),
            'income' => (float) Payment::where('school_id', $school->id)->where('status', 'pagado')->sum('amount'),
        ];

        $admins = User::where('school_id', $school->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->get();

        return view('schools.show', compact('school', 'stats', 'admins'));
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'plan' => ['required', 'in:basico,pro,institucional'],
            'status' => ['required', 'in:activo,suspendido'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
        ]);

        $school->update($data);

        return redirect()->route('schools.show', $school)->with('success', 'Colegio actualizado.');
    }
}
