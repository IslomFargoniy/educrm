<?php

namespace App\Http\Controllers;

use App\Jobs\SyncStudentFace;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The student's face photo for the terminals (kept private; terminals fetch it through a signed link).
 */
class StudentFaceController extends Controller
{
    public function store(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($request, $student);
        $request->validate(['photo' => ['required', 'image', 'max:10240']]);

        $path = 'faces/'.$student->id.'-'.Str::random(12).'.jpg';
        Storage::disk('local')->put($path, $this->normalize($request->file('photo')));

        if ($student->face_photo !== null) {
            Storage::disk('local')->delete($student->face_photo);
        }

        $student->update(['face_photo' => $path]);
        SyncStudentFace::dispatch($student->id);
        $this->toast(__('Photo saved. It is being sent to the devices.'));

        return back();
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($request, $student);

        if ($student->face_photo !== null) {
            Storage::disk('local')->delete($student->face_photo);
            $student->update(['face_photo' => null]);
        }

        SyncStudentFace::dispatch($student->id);
        $this->toast(__('Photo deleted. The student is removed from the devices.'));

        return back();
    }

    /**
     * Shown to staff on the student page.
     */
    public function show(Request $request, Student $student): StreamedResponse
    {
        $this->authorizeStudent($request, $student);

        return $this->photo($student);
    }

    /**
     * Downloaded by a terminal through the signed link FaceSync sends it.
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

    /**
     * JPEG the terminals accept: turned upright, at most 640 px, under ~200 KB.
     */
    private function normalize(UploadedFile $file): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($image === false) {
            throw ValidationException::withMessages(['photo' => __('The photo could not be read.')]);
        }

        $exif = function_exists('exif_read_data') ? @exif_read_data($file->getRealPath()) : false;
        $angle = match (is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $image = imagerotate($image, $angle, 0) ?: $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 640 / max($width, $height));

        if ($scale < 1) {
            $image = imagescale($image, (int) round($width * $scale), (int) round($height * $scale)) ?: $image;
        }

        $jpeg = '';

        foreach ([88, 80, 70, 60] as $quality) {
            ob_start();
            imagejpeg($image, null, $quality);
            $jpeg = (string) ob_get_clean();

            if (strlen($jpeg) <= 200 * 1024) {
                break;
            }
        }

        return $jpeg;
    }

    private function authorizeStudent(Request $request, Student $student): void
    {
        abort_unless($request->user()->can('students.update') && Student::query()->whereKey($student->id)->inBranches($request->user()->accessibleBranchIds())->exists(), 403);
    }
}
