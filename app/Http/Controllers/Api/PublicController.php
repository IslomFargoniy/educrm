<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Lead;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public data for the mensalc.uz landing page and its sign-up form.
 */
class PublicController extends Controller
{
    /**
     * Courses (from active groups) and branch contacts.
     */
    public function info(): JsonResponse
    {
        $courses = Group::query()->where('status', 'active')->with('prices', 'branch:id,name')->get()
            ->groupBy(fn (Group $g) => $g->subject ?: $g->name)
            ->map(function ($groups, $subject) {
                $prices = $groups->map(fn (Group $g) => $g->priceFor(today()))->filter();

                return [
                    'name' => $subject,
                    'price_from' => $prices->min('monthly_price'),
                    'lessons_per_month' => $prices->min('lessons_per_month'),
                    'groups' => $groups->count(),
                    'days' => $groups->flatMap(fn (Group $g) => $g->days)->unique()->sort()->values(),
                ];
            })->values();

        return response()->json([
            'courses' => $courses,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'address', 'phone']),
        ]);
    }

    /**
     * Sign-up request from the website: becomes a new lead in the CRM.
     */
    public function lead(Request $request): JsonResponse
    {
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true]);
        }

        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^\+998\d{9}$/'],
            'course' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $branchId = $validated['branch_id'] ?? Branch::query()->where('is_active', true)->value('id');
        abort_if($branchId === null, 503);

        Lead::query()->create([
            'branch_id' => $branchId,
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'],
            'source' => LeadSource::Website,
            'status' => LeadStatus::New,
            'interested_course' => $validated['course'] ?? null,
            'note' => $validated['note'] ?? null,
            'next_contact_at' => today(),
        ]);

        return response()->json(['ok' => true], 201);
    }
}
