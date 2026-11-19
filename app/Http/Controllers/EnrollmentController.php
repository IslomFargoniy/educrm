<?php

namespace App\Http\Controllers;

use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;
use App\Services\BillingService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EnrollmentController extends Controller
{
    public function __construct(private EnrollmentService $enrollments, private FinanceService $finance) {}

    public function store(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'joined_at' => ['required', 'date'],
            'trial_lessons' => ['nullable', 'integer', 'min:0', 'max:30'],
            ...$this->termRules(),
        ]);
        $group = Group::query()->findOrFail($request->integer('group_id'));
        $this->authorizeBranch($group->branch_id);

        $this->enrollments->enroll($student, $group, Carbon::parse($validated['joined_at']), [
            'trial_lessons' => (int) ($validated['trial_lessons'] ?? 0),
            ...$this->termsFrom($request, $validated),
        ]);
        $this->toast(__('Student added to the group.'));

        return back();
    }

    public function update(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);
        $validated = $request->validate($this->termRules());
        $this->enrollments->updateTerms($enrollment, $this->termsFrom($request, $validated));
        $this->toast(__('Saved.'));

        return back();
    }

    public function transfer(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);
        $validated = $request->validate(['group_id' => ['required', 'exists:groups,id'], 'date' => ['required', 'date']]);
        $group = Group::query()->findOrFail($request->integer('group_id'));
        $this->authorizeBranch($group->branch_id);

        $this->enrollments->transfer($enrollment, $group, Carbon::parse($validated['date']));
        $this->toast(__('Student moved to :group.', ['group' => $group->name]));

        return back();
    }

    public function leave(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'reason' => ['required', 'in:graduated,dropped,expelled'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->enrollments->leave($enrollment, Carbon::parse($validated['date']), $validated['reason'], $validated['note'] ?? null);
        $this->toast($validated['reason'] === 'graduated' ? __('The student graduated from the group.') : __('Student removed from the group.'));

        return back();
    }

    public function freeze(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);
        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $this->enrollments->freeze($enrollment, Carbon::parse($validated['from_date']), Carbon::parse($validated['to_date']), $validated['reason'] ?? null);
        $this->toast(__('Student frozen.'));

        return back();
    }

    public function unfreeze(Enrollment $enrollment, int $freeze): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);
        $enrollment->freezes()->whereKey($freeze)->update(['to_date' => today()->subDay()]);

        return back();
    }

    public function trialDecision(Request $request, Enrollment $enrollment, BillingService $billing): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);
        $validated = $request->validate(['continues' => ['required', 'boolean']]);
        $billing->decideTrial($enrollment, (bool) $validated['continues']);
        $this->toast($validated['continues'] ? __('Student continues. Trial lessons are billed.') : __('Trial finished. Trial lessons are not billed.'));

        return back();
    }

    public function payment(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);
        $validated = $request->validate([
            'cashbox_id' => ['required', 'exists:cashboxes,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);
        $cashbox = Cashbox::query()->findOrFail($request->integer('cashbox_id'));

        $payment = $this->finance->receivePayment($enrollment, $cashbox, (int) $validated['amount'], $validated['note'] ?? null, null, $validated['idempotency_key'] ?? null);
        $this->toast(__('Payment received. Receipt :no.', ['no' => $payment->receipt_no]));

        return back()->with('receipt', $payment->id);
    }

    public function refund(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);
        $validated = $request->validate([
            'cashbox_id' => ['required', 'exists:cashboxes,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->finance->refund($enrollment, Cashbox::query()->findOrFail($request->integer('cashbox_id')), (int) $validated['amount'], $validated['note'] ?? null);
        $this->toast(__('Money returned.'));

        return back();
    }

    public function transferBalance(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);
        $validated = $request->validate([
            'to_enrollment_id' => ['required', 'exists:enrollments,id'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);
        $target = Enrollment::query()->findOrFail($request->integer('to_enrollment_id'));
        $this->authorizeEnrollment($target);
        $this->finance->transferBalance($enrollment, $target, (int) $validated['amount']);
        $this->toast(__('Balance moved.'));

        return back();
    }

    public function writeOff(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $amount = $this->finance->writeOffDebt($enrollment, $validated['reason']);
        $this->toast(__('Debt written off: :amount.', ['amount' => number_format($amount, 0, '.', ' ')]));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function termRules(): array
    {
        return [
            'discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value' => ['nullable', 'integer', 'min:0'],
            'teacher_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{discount_type: string|null, discount_value: int, teacher_percent: int|null}
     */
    private function termsFrom(Request $request, array $validated): array
    {
        $terms = [
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_value' => (int) ($validated['discount_value'] ?? 0),
            'teacher_percent' => isset($validated['teacher_percent']) ? (int) $validated['teacher_percent'] : null,
        ];

        if (! $request->user()->can('groups.prices')) {
            $terms['teacher_percent'] = null;
        }

        return $terms;
    }

    private function authorizeEnrollment(Enrollment $enrollment): void
    {
        $this->authorizeBranch($enrollment->group->branch_id);
    }
}
