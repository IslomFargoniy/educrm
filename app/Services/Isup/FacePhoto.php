<?php

namespace App\Services\Isup;

use App\Jobs\SyncStudentFace;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The student's face photo for the terminals: who may change it, and how it is stored and sent.
 */
class FacePhoto
{
    /**
     * Staff with students.update (in their branches), or a teacher with students.face for students of own groups.
     */
    public function canManage(User $user, Student $student): bool
    {
        if ($user->can('students.update')) {
            return Student::query()->whereKey($student->id)->inBranches($user->accessibleBranchIds())->exists();
        }

        if (! $user->can('students.face')) {
            return false;
        }

        return $student->enrollments()
            ->whereIn('status', ['trial', 'active'])
            ->whereHas('group', fn ($q) => $q->where('teacher_id', $user->id))
            ->exists()
            || ($user->can('groups.view') && Student::query()->whereKey($student->id)->inBranches($user->accessibleBranchIds())->exists());
    }

    public function save(Student $student, UploadedFile $file): void
    {
        $path = 'faces/'.$student->id.'-'.Str::random(12).'.jpg';
        Storage::disk('local')->put($path, $this->normalize($file));

        if ($student->face_photo !== null) {
            Storage::disk('local')->delete($student->face_photo);
        }

        $student->update(['face_photo' => $path]);
        SyncStudentFace::dispatch($student->id);
    }

    public function delete(Student $student): void
    {
        if ($student->face_photo !== null) {
            Storage::disk('local')->delete($student->face_photo);
            $student->update(['face_photo' => null]);
        }

        SyncStudentFace::dispatch($student->id);
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
}
