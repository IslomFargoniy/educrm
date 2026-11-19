<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/courses', [
            'courses' => Course::query()->withCount('groups')->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Course::query()->create($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('courses', 'name')],
        ]));
        $this->toast(__('Course added.'));

        return back();
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $course->update($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('courses', 'name')->ignore($course->id)],
            'is_active' => ['boolean'],
        ]));
        $this->toast(__('Saved.'));

        return back();
    }
}
