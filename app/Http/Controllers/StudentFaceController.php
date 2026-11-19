<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\Isup\FacePhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The student's face photo for the terminals (kept private; terminals and the app fetch it through a signed link).
 */
class StudentFaceController extends Controller
{
    public function __construct(private FacePhoto $faces) {}

    public function store(Request $request, Student $student): RedirectResponse
    {
        abort_unless($this->faces->canManage($request->user(), $student), 403);
        $request->validate(['photo' => ['required', 'image', 'max:10240']]);
        $this->faces->save($student, $request->file('photo'));
        $this->toast(__('Photo saved. It is being sent to the devices.'));

        return back();
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        abort_unless($this->faces->canManage($request->user(), $student), 403);
        $this->faces->delete($student);
        $this->toast(__('Photo deleted. The student is removed from the devices.'));

        return back();
    }

    /**
     * Shown to staff on the student page.
     */
    public function show(Request $request, Student $student): StreamedResponse
    {
        abort_unless($this->faces->canManage($request->user(), $student), 403);

        return $this->photo($student);
    }

    /**
     * Downloaded by a terminal (or the app) through a signed link.
     */
    public function forDevice(Student $student): StreamedResponse
    {
        return $this->photo($student);
    }

    private function photo(Student $student): StreamedResponse
    {
        abort_if($student->face_photo === null || ! Storage::disk('local')->exists($student->face_photo), 404);

        return Storage::disk('local')->response($student->face_photo, 'face.jpg', ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=300']);
    }
}
