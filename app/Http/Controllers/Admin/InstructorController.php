<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInstructorRequest;
use App\Models\Faculty;

class InstructorController extends Controller
{
    public function create()
    {
        return view('admin.instructors.create');
    }

    public function store(StoreInstructorRequest $request)
    {
        $data = $request->validated();

        Faculty::create([
            'name' => trim($data['name']),
            'designation' => trim($data['designation']),
        ]);

        return redirect()->route('admin.instructor-assignments.index')
            ->with('success', 'Instructor added to the assignment list.');
    }
}
