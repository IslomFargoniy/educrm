<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\BillingService;
use App\Support\Presenters\History;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    /**
     * Parent / student cabinet: every child with balances, attendance and payments.
     */
    public function __invoke(Request $request, BillingService $billing): Response
    {
        return Inertia::render('portal', [
            'children' => $this->studentsOf($request)->map(fn (Student $student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'groups' => $student->enrollments()->with('group.teacher:id,name')->whereIn('status', ['active', 'trial'])->get()
                    ->map(function (Enrollment $enrollment) use ($billing) {
                        $price = $enrollment->group->priceFor(today());
                        $month = today()->format('Y-m');

                        return [
                            'id' => $enrollment->id,
                            'group' => $enrollment->group->name,
                            'teacher' => $enrollment->group->teacher?->name,
                            'days' => $enrollment->group->days,
                            'time' => substr($enrollment->group->start_time, 0, 5).'–'.substr($enrollment->group->end_time, 0, 5),
                            'balance' => $enrollment->balance,
                            'monthly_price' => $price ? $enrollment->discountedMonthlyPrice($price->monthly_price) : null,
                            'billed_this_month' => $billing->billedLessonsInMonth($enrollment, $month),
                            'lessons_per_month' => $price?->lessons_per_month,
                            'history' => History::forEnrollment($enrollment, now()->subMonthsNoOverflow(2)->startOfMonth(), today()),
                        ];
                    }),
            ]),
        ]);
    }

    /**
     * @return Collection<int, Student>
     */
    private function studentsOf(Request $request): Collection
    {
        $user = $request->user();

        return $user->children()->get()
            ->merge($user->studentProfile ? [$user->studentProfile] : [])
            ->unique('id')
            ->values();
    }
}
