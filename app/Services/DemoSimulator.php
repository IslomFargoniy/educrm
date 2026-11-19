<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\CashboxMethod;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LedgerAccount;
use App\Enums\LessonStatus;
use App\Enums\Role;
use App\Events\DebtStarted;
use App\Events\PaymentReceived;
use App\Events\StudentAbsent;
use App\Models\Branch;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\ExpenseCategory;
use App\Models\Group;
use App\Models\Holiday;
use App\Models\Lead;
use App\Models\LedgerEntry;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Fills a separate "Demo" branch with a realistic history of a learning center, day by day,
 * through the real services (attendance, billing, payments, payouts), and removes it again.
 *
 * Every demo phone number starts with +998000, a range no operator uses, so demo
 * accounts never collide with real people.
 */
class DemoSimulator
{
    public const BRANCH_NAME = 'Demo filial';

    public const PHONE_PREFIX = '+998000';

    /** @var list<string> */
    private const MALE = ['Ali', 'Sardor', 'Jasur', 'Akmal', 'Shoxrux', 'Dilshod', 'Bekzod', 'Otabek', 'Farrux', 'Javohir', 'Aziz', 'Bobur', 'Sanjar', 'Ulug\'bek', 'Doston', 'Islom', 'Abdulla', 'Muhammad', 'Sherzod', 'Temur', 'Asadbek', 'Behruz', 'Diyorbek', 'Elbek', 'Firdavs'];

    /** @var list<string> */
    private const FEMALE = ['Madina', 'Nilufar', 'Zarina', 'Kamola', 'Sevara', 'Laylo', 'Malika', 'Dilnoza', 'Gulnoza', 'Mohinur', 'Shahzoda', 'Sabina', 'Feruza', 'Nodira', 'Charos', 'Munisa', 'Zilola', 'Mohira', 'Durdona', 'Hilola', 'Robiya', 'Sitora', 'Yulduz', 'Oydin', 'Marjona'];

    /** @var list<string> */
    private const SURNAMES = ['Karimov', 'Aliyev', 'Rasulov', 'Bekov', 'Saidov', 'Qodirov', 'Umarov', 'Ergashev', 'Rahimov', 'Ismoilov', 'Nurmatov', 'Tursunov', 'Yuldashev', 'Hamidov', 'Sobirov', 'Toshev', 'Yusupov', 'Abdullayev', 'Mirzayev', 'Xolmatov', 'Sultonov', 'Normatov', 'Jo\'rayev', 'Hasanov', 'Ortiqov'];

    /**
     * name, subject, days, start, end, monthly price, lessons per month, teacher index, room index, teacher %, starts in month.
     *
     * @var list<array{0: string, 1: string, 2: list<int>, 3: string, 4: string, 5: int, 6: int, 7: int, 8: int, 9: int, 10: int}>
     */
    private const GROUPS = [
        ['Ingliz Beginner', 'English', [1, 3, 5], '09:00', '10:30', 480_000, 12, 0, 0, 40, 0],
        ['Ingliz Elementary', 'English', [2, 4, 6], '09:00', '10:30', 540_000, 12, 0, 0, 40, 0],
        ['Ingliz Pre-IELTS', 'English', [1, 3, 5], '14:00', '15:30', 660_000, 12, 1, 1, 40, 0],
        ['IELTS intensiv', 'IELTS', [1, 2, 3, 4, 5], '18:00', '19:30', 1_000_000, 20, 1, 1, 35, 0],
        ['Matematika 5-6', 'Matematika', [2, 4, 6], '14:00', '15:30', 450_000, 12, 2, 2, 50, 0],
        ['Matematika DTM', 'Matematika', [1, 3, 5], '16:00', '17:30', 600_000, 12, 2, 2, 45, 0],
        ['Rus tili', 'Rus tili', [2, 4, 6], '16:00', '17:30', 360_000, 12, 3, 0, 50, 0],
        ['Koreys tili', 'Koreys tili', [1, 3, 5], '18:00', '19:30', 720_000, 12, 4, 2, 40, 1],
    ];

    /** @var list<array{0: string, 1: string}> */
    private const STAFF = [
        ['Aziza Karimova', 'teacher'],
        ['Jamshid Toshev', 'teacher'],
        ['Rustam Yusupov', 'teacher'],
        ['Olga Petrova', 'teacher'],
        ['Kim Su Min', 'teacher'],
        ['Demo Admin', 'admin'],
        ['Demo Kassir', 'manager'],
    ];

    private User $actor;

    private Branch $branch;

    /** @var array<string, Cashbox> */
    private array $cashboxes = [];

    /** @var list<Group> */
    private array $groups = [];

    /** @var array<int, array{profile: string, dueDay: int}> keyed by enrollment id */
    private array $payers = [];

    /** @var list<array{day: string, action: \Closure, when: string}> */
    private array $events = [];

    private int $phoneSeq = 0;

    /** @var array{students: int, enrollments: int, lessons_held: int, attendance: int, payments: int, payments_sum: int, expenses_sum: int, payouts_sum: int, leads: int} */
    private array $stats = ['students' => 0, 'enrollments' => 0, 'lessons_held' => 0, 'attendance' => 0, 'payments' => 0, 'payments_sum' => 0, 'expenses_sum' => 0, 'payouts_sum' => 0, 'leads' => 0];

    public function __construct(
        private LessonScheduler $scheduler,
        private EnrollmentService $enrollments,
        private AttendanceService $attendance,
        private BillingService $billing,
        private FinanceService $finance,
        private CoverageService $coverage,
    ) {}

    public function exists(): bool
    {
        return Branch::query()->where('name', self::BRANCH_NAME)->exists();
    }

    /**
     * Simulate the last $months months up to yesterday; today's and future lessons stay planned.
     *
     * @return array{students: int, enrollments: int, lessons_held: int, attendance: int, payments: int, payments_sum: int, expenses_sum: int, payouts_sum: int, leads: int}
     */
    public function simulate(int $studentCount = 100, int $months = 3, int $seed = 2026): array
    {
        mt_srand($seed);
        $today = CarbonImmutable::today();
        $start = $today->subMonthsNoOverflow($months)->startOfWeek();
        $previousNow = Carbon::getTestNow();

        try {
            // One transaction: a failure leaves nothing behind.
            Event::fakeFor(fn () => DB::transaction(function () use ($studentCount, $months, $start, $today) {
                $this->setNow($start->setTime(8, 0));
                $this->setUp($start, $today, $months);
                $this->planStudents($studentCount, $start, $today);
                $this->planLeads($start, $today);
                $this->planFinance($start, $today);
                $this->run($start, $today);
            }), [PaymentReceived::class, StudentAbsent::class, DebtStarted::class]);
        } finally {
            Carbon::setTestNow($previousNow);
            CarbonImmutable::setTestNow($previousNow);
            Auth::logout();
        }

        return $this->stats;
    }

    /**
     * Delete the demo branch and everything that belongs to it, including demo accounts.
     *
     * @return array{transactions: int, students: int, users: int}
     */
    public function purge(): array
    {
        return DB::transaction(function () {
            $branch = Branch::query()->where('name', self::BRANCH_NAME)->first();
            $userIds = User::query()->where('phone', 'like', self::PHONE_PREFIX.'%')->pluck('id');

            if ($branch === null && $userIds->isEmpty()) {
                return ['transactions' => 0, 'students' => 0, 'users' => 0];
            }

            $studentIds = Student::query()->where('branch_id', $branch?->id)->pluck('id');
            $enrollmentIds = Enrollment::query()->whereIn('student_id', $studentIds)->pluck('id');
            $cashboxIds = Cashbox::query()->where('branch_id', $branch?->id)->pluck('id');

            $transactionIds = Transaction::query()->where('branch_id', $branch?->id)->pluck('id')
                ->merge(LedgerEntry::query()->where(fn ($q) => $q
                    ->where(fn ($q) => $q->where('account_type', LedgerAccount::Enrollment)->whereIn('account_id', $enrollmentIds))
                    ->orWhere(fn ($q) => $q->where('account_type', LedgerAccount::Cashbox)->whereIn('account_id', $cashboxIds))
                    ->orWhere(fn ($q) => $q->where('account_type', LedgerAccount::Teacher)->whereIn('account_id', $userIds)))
                    ->pluck('transaction_id'))
                ->unique()->values();

            Payment::query()->whereIn('enrollment_id', $enrollmentIds)->delete();
            Transaction::query()->whereIn('reverses_id', $transactionIds)->update(['reverses_id' => null]);
            $transactions = Transaction::query()->whereIn('id', $transactionIds)->delete();
            $students = Student::query()->whereIn('id', $studentIds)->delete();
            $branch?->delete();
            $users = User::query()->whereIn('id', $userIds)->delete();

            return ['transactions' => $transactions, 'students' => $students, 'users' => $users];
        });
    }

    private function setUp(CarbonImmutable $start, CarbonImmutable $today, int $months): void
    {
        $this->branch = Branch::query()->create(['name' => self::BRANCH_NAME, 'address' => 'Toshkent, Yunusobod 4-kvartal', 'phone' => $this->phone()]);
        $rooms = collect(['A-1', 'A-2', 'B-1'])->map(fn (string $name) => $this->branch->rooms()->create(['name' => $name, 'capacity' => 16]));

        foreach (['naqd' => [CashboxMethod::Cash, 'Naqd'], 'karta' => [CashboxMethod::Card, 'Karta'], 'click' => [CashboxMethod::Click, 'Click'], 'payme' => [CashboxMethod::Payme, 'Payme']] as $key => [$method, $name]) {
            $this->cashboxes[$key] = $this->branch->cashboxes()->create(['name' => $name, 'method' => $method]);
        }

        $staff = collect(self::STAFF)->map(fn (array $person) => $this->account($person[0], Role::from($person[1]), true));
        $teachers = $staff->filter(fn (User $user) => $user->hasRole(Role::Teacher->value))->values();
        $this->actor = $staff->first(fn (User $user) => $user->hasRole(Role::Admin->value));
        Auth::login($this->actor);

        foreach (self::GROUPS as $g) {
            $groupStart = $start->addMonthsNoOverflow($g[10])->startOfWeek();
            $group = Group::query()->create([
                'branch_id' => $this->branch->id, 'name' => $g[0], 'subject' => $g[1], 'days' => $g[2],
                'start_time' => $g[3], 'end_time' => $g[4], 'teacher_id' => $teachers[$g[7]]->id,
                'room_id' => $rooms[$g[8]]->id, 'teacher_percent' => $g[9], 'start_date' => $groupStart,
            ]);
            $group->prices()->create(['monthly_price' => $g[5], 'lessons_per_month' => $g[6], 'effective_from' => $groupStart->startOfMonth(), 'created_by' => $this->actor->id]);
            $this->scheduler->generate($group, $groupStart, $today->addMonthNoOverflow()->endOfMonth());
            $this->groups[] = $group;
        }

        // National holidays inside the period close the center for the day.
        foreach (range(0, $months) as $i) {
            $year = $start->addMonthsNoOverflow($i)->year;

            foreach (["{$year}-09-01" => 'Mustaqillik kuni', "{$year}-10-01" => 'O\'qituvchilar kuni', "{$year}-12-08" => 'Konstitutsiya kuni'] as $date => $name) {
                $day = CarbonImmutable::parse($date);

                if ($day->betweenIncluded($start, $today->addMonthNoOverflow()) && ! Holiday::query()->where('branch_id', $this->branch->id)->whereDate('date', $date)->exists()) {
                    $this->scheduler->applyHoliday(Holiday::query()->create(['branch_id' => $this->branch->id, 'date' => $date, 'name' => $name]));
                }
            }
        }
    }

    private function planStudents(int $count, CarbonImmutable $start, CarbonImmutable $today): void
    {
        $days = max(1, (int) $start->diffInDays($today));

        for ($i = 0; $i < $count; $i++) {
            $female = $i % 2 === 1;
            $first = $female ? self::FEMALE[mt_rand(0, count(self::FEMALE) - 1)] : self::MALE[mt_rand(0, count(self::MALE) - 1)];
            $surname = self::SURNAMES[mt_rand(0, count(self::SURNAMES) - 1)].($female ? 'a' : '');
            $name = "{$first} {$surname}";
            // 65% start with the period, the rest join later.
            $joinOffset = mt_rand(1, 100) <= 65 ? mt_rand(0, 6) : mt_rand(7, (int) max(8, $days - 10));
            $groupIndex = $i % count(self::GROUPS);
            $joinDay = $start->addDays($joinOffset);
            $group = self::GROUPS[$groupIndex];
            $groupStart = $start->addMonthsNoOverflow($group[10])->startOfWeek();

            if ($joinDay->lessThan($groupStart)) {
                $joinDay = $groupStart->addDays(mt_rand(0, 4));
            }

            $roll = mt_rand(1, 100);
            $profile = match (true) {
                $roll <= 58 => 'ontime',
                $roll <= 80 => 'late',
                $roll <= 92 => 'partial',
                default => 'debtor',
            };
            $terms = match (true) {
                $i % 11 === 3 => ['discount_type' => 'percent', 'discount_value' => 10],
                $i % 17 === 5 => ['discount_type' => 'amount', 'discount_value' => 60_000],
                $i % 9 === 4 => ['trial_lessons' => 2],
                default => [],
            };

            $this->at($joinDay, function () use ($name, $female, $groupIndex, $joinDay, $terms, $profile, $i, $today) {
                $student = Student::query()->create([
                    'full_name' => $name,
                    'phone' => $this->phone(),
                    'birth_date' => $joinDay->subYears(mt_rand(10, 24))->subDays(mt_rand(0, 360)),
                    'branch_id' => $this->branch->id,
                ]);
                $parent = $this->account(explode(' ', $name)[1].' '.($female ? 'onasi' : 'otasi'), Role::Parent, false);
                $student->guardians()->attach($parent->id, ['relation' => $female ? 'mother' : 'father']);
                $this->stats['students']++;

                $enrollment = $this->enroll($student, $this->groups[$groupIndex], $joinDay, $terms, $profile);

                if (($terms['trial_lessons'] ?? 0) > 0) {
                    $this->at($joinDay->addDays(6), function () use ($enrollment, $i) {
                        $fresh = $enrollment->fresh();

                        if ($fresh?->status === EnrollmentStatus::Trial) {
                            $this->billing->decideTrial($fresh, $i % 4 !== 0);
                        }
                    });
                }

                // Every 8th student also takes a second subject.
                if ($i % 8 === 2) {
                    $this->enroll($student, $this->groups[($groupIndex + 4) % count($this->groups)], $joinDay->addDays(mt_rand(3, 20)), [], 'ontime');
                }

                $this->planLifeEvents($enrollment, $i, $joinDay, $today);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function enroll(Student $student, Group $group, CarbonImmutable $date, array $terms, string $profile): Enrollment
    {
        $date = $date->max(CarbonImmutable::parse($group->start_date));
        $enrollment = $this->enrollments->enroll($student, $group, $date, $terms);
        $this->payers[$enrollment->id] = ['profile' => $profile, 'dueDay' => $date->day];
        $this->stats['enrollments']++;

        if (($terms['trial_lessons'] ?? 0) === 0) {
            $this->pay($enrollment, $profile === 'partial' ? 0.5 : 1.0);
        }

        return $enrollment;
    }

    private function planLifeEvents(Enrollment $enrollment, int $i, CarbonImmutable $joinDay, CarbonImmutable $today): void
    {
        $mid = $joinDay->addDays(mt_rand(20, 45));

        if ($mid->greaterThanOrEqualTo($today)) {
            return;
        }

        match (true) {
            // Freeze for one to two weeks (illness, trip).
            $i % 23 === 7 => $this->at($mid, fn () => $this->whenActive($enrollment, fn (Enrollment $e) => $this->enrollments->freeze($e, $mid, $mid->addDays(mt_rand(7, 14)), $i % 2 ? 'Kasal' : 'Safarda'))),
            // Moves to another group of the same subject.
            $i % 29 === 11 => $this->at($mid, fn () => $this->whenActive($enrollment, function (Enrollment $e) use ($mid) {
                $target = collect($this->groups)->first(fn (Group $g) => $g->subject === $e->group->subject && $g->id !== $e->group_id);

                if ($target && CarbonImmutable::parse($target->start_date)->lessThanOrEqualTo($mid)) {
                    $new = $this->enrollments->transfer($e, $target, $mid);
                    $this->payers[$new->id] = $this->payers[$e->id] ?? ['profile' => 'ontime', 'dueDay' => $mid->day];
                }
            })),
            // Leaves the center; prepaid money is refunded.
            $i % 19 === 13 => $this->at($mid, fn () => $this->whenActive($enrollment, function (Enrollment $e) use ($mid) {
                $this->enrollments->leave($e, $mid);
                $available = $this->coverage->availableFunds($e->fresh());

                if ($available > 0) {
                    $this->finance->refund($e->fresh(), $this->cashboxes['naqd']->fresh(), $available, 'Markazni tark etdi');
                }
            })),
            default => null,
        };
    }

    private function whenActive(Enrollment $enrollment, \Closure $action): void
    {
        $fresh = $enrollment->fresh();

        if ($fresh !== null && $fresh->status === EnrollmentStatus::Active) {
            $action($fresh);
        }
    }

    private function planLeads(CarbonImmutable $start, CarbonImmutable $today): void
    {
        $sources = LeadSource::cases();
        $courses = ['English', 'IELTS', 'Matematika', 'Rus tili', 'Koreys tili'];
        $days = (int) $start->diffInDays($today);

        for ($i = 0; $i < 70; $i++) {
            $day = $start->addDays(mt_rand(0, max(0, $days - 1)));
            $age = (int) $day->diffInDays($today);
            $status = match (true) {
                $age < 5 => LeadStatus::New,
                $age < 12 => mt_rand(0, 1) ? LeadStatus::Contacted : LeadStatus::New,
                default => [LeadStatus::Converted, LeadStatus::Converted, LeadStatus::Rejected, LeadStatus::Contacted, LeadStatus::Trial][mt_rand(0, 4)],
            };
            $name = ($i % 2 ? self::FEMALE[mt_rand(0, 24)] : self::MALE[mt_rand(0, 24)]).' '.self::SURNAMES[mt_rand(0, 24)].($i % 2 ? 'a' : '');

            $this->at($day, function () use ($name, $sources, $courses, $status, $day, $i) {
                $lead = Lead::query()->create([
                    'branch_id' => $this->branch->id, 'full_name' => $name, 'phone' => $this->phone(),
                    'source' => $sources[mt_rand(0, count($sources) - 1)], 'interested_course' => $courses[mt_rand(0, 4)],
                    'status' => $status, 'assigned_to' => $this->actor->id,
                    'next_contact_at' => in_array($status, [LeadStatus::New, LeadStatus::Contacted], true) ? $day->addDays(mt_rand(1, 5)) : null,
                ]);

                if ($status !== LeadStatus::New) {
                    $lead->notes()->create(['user_id' => $this->actor->id, 'text' => ['Qo\'ng\'iroq qilindi, narxlar aytildi', 'Sinov darsiga yozildi', 'Keyinroq o\'ylab ko\'rishini aytdi', 'Telegramdan yozildi'][$i % 4]]);
                }

                $this->stats['leads']++;
            });
        }
    }

    private function planFinance(CarbonImmutable $start, CarbonImmutable $today): void
    {
        $rent = ExpenseCategory::query()->firstOrCreate(['name' => 'Ijara']);
        $utilities = ExpenseCategory::query()->firstOrCreate(['name' => 'Kommunal']);
        $ads = ExpenseCategory::query()->firstOrCreate(['name' => 'Reklama']);
        $office = ExpenseCategory::query()->firstOrCreate(['name' => 'Kanselyariya']);

        foreach (CarbonPeriod::create($start, $today->subDay()) as $date) {
            $day = CarbonImmutable::parse($date);

            if ($day->day === 3) {
                $this->at($day, fn () => $this->expense('naqd', $rent, 4_000_000, 'Ijara, '.$day->translatedFormat('F')), 'evening');
            }

            if ($day->day === 10) {
                $this->at($day, fn () => $this->expense('karta', $utilities, mt_rand(35, 55) * 10_000, 'Svet, gaz, internet'), 'evening');
                $this->at($day, fn () => $this->expense('click', $ads, 600_000, 'Instagram reklama'), 'evening');
            }

            if ($day->day === 15) {
                $this->at($day, fn () => $this->expense('naqd', $office, mt_rand(10, 25) * 10_000, 'Daftar, marker, qog\'oz'), 'evening');
            }

            // Weekly cash collection from online cashboxes.
            if ($day->isSaturday()) {
                $this->at($day, function () {
                    foreach (['click', 'payme', 'karta'] as $key) {
                        $balance = $this->cashboxes[$key]->fresh()->balance;

                        if ($balance > 1_000_000) {
                            $this->finance->transferBetweenCashboxes($this->cashboxes[$key]->fresh(), $this->cashboxes['naqd']->fresh(), $balance - 500_000, 'Inkassatsiya');
                        }
                    }
                }, 'evening');
            }

            // Salaries on the 5th for everything earned so far.
            if ($day->day === 5 && $day->greaterThan($start->addDays(20))) {
                $this->at($day, function () {
                    User::query()->where('phone', 'like', self::PHONE_PREFIX.'%')->role(Role::Teacher->value)->get()
                        ->each(function (User $teacher) {
                            $amount = min($teacher->balance, $this->cashboxes['naqd']->fresh()->balance);

                            if ($amount > 0) {
                                $this->finance->payTeacher($teacher, $this->cashboxes['naqd']->fresh(), $amount, 'Oylik');
                                $this->stats['payouts_sum'] += $amount;
                            }
                        });
                }, 'evening');
            }

            // Monthly fees: on-time payers on their due day, late payers a week later.
            $this->at($day, function () use ($day) {
                foreach ($this->payers as $enrollmentId => $payer) {
                    $shift = $payer['profile'] === 'late' ? mt_rand(5, 12) : 0;
                    $dueDay = min(28, $payer['dueDay']);
                    $isDueToday = $payer['profile'] !== 'debtor' && ($day->subDays($shift)->day === $dueDay);

                    if (! $isDueToday) {
                        continue;
                    }

                    $enrollment = Enrollment::query()->find($enrollmentId);

                    if ($enrollment === null || $enrollment->status !== EnrollmentStatus::Active
                        || CarbonImmutable::parse($enrollment->joined_at)->diffInDays($day) < 20) {
                        continue;
                    }

                    $this->pay($enrollment, $payer['profile'] === 'partial' ? 0.5 : 1.0);
                }
            });
        }

        // A couple of mistaken payments get reversed and re-entered.
        foreach ([12, 40] as $offset) {
            $day = $start->addDays($offset);

            if ($day->lessThan($today)) {
                $this->at($day, function () {
                    $payment = Payment::query()->whereIn('cashbox_id', collect($this->cashboxes)->pluck('id'))->whereNull('reversed_at')->latest('id')->first();

                    if ($payment !== null && $payment->cashbox->balance >= $payment->amount) {
                        $enrollment = $payment->enrollment;
                        $cashbox = $payment->cashbox;
                        $amount = $payment->amount;
                        $this->finance->reversePayment($payment, 'Boshqa o\'quvchiga yozilib ketgan');
                        $this->finance->receivePayment($enrollment->fresh(), $cashbox->fresh(), $amount, 'Qayta kiritildi');
                    }
                }, 'evening');
            }
        }

        // One old debt is forgiven near the end.
        $this->at($today->subDays(2), function () {
            $debtor = Enrollment::query()->whereIn('group_id', collect($this->groups)->pluck('id'))->where('balance', '<', 0)->orderBy('balance')->first();

            if ($debtor !== null) {
                $this->finance->writeOffDebt($debtor, 'Ijtimoiy holat, rahbariyat qarori');
            }
        }, 'evening');

        // A few lessons are taught by a substitute.
        foreach ([9, 33, 61] as $offset) {
            $day = $start->addDays($offset);
            $this->at($day, function () use ($day) {
                $lesson = Lesson::query()->whereIn('group_id', collect($this->groups)->pluck('id'))->whereDate('date', $day)->where('status', LessonStatus::Planned)->first();
                $substitute = User::query()->where('phone', 'like', self::PHONE_PREFIX.'%')->role(Role::Teacher->value)->where('id', '!=', $lesson?->teacher_id)->first();

                if ($lesson !== null && $substitute !== null) {
                    $lesson->update(['teacher_id' => $substitute->id, 'is_substitute' => true]);
                }
            });
        }
    }

    private function run(CarbonImmutable $start, CarbonImmutable $today): void
    {
        foreach (CarbonPeriod::create($start, $today->subDay()) as $date) {
            $day = CarbonImmutable::parse($date)->toDateString();
            $this->setNow(CarbonImmutable::parse($day.' 08:00:00'));
            $this->fire($day, 'morning');

            $this->setNow(CarbonImmutable::parse($day.' 20:00:00'));
            $this->markLessons($day);
            $this->fire($day, 'evening');
        }
    }

    private function fire(string $day, string $when): void
    {
        // Events can schedule further events for the same day, so read the queue live.
        $done = 0;

        while (true) {
            $todays = collect($this->events)->filter(fn (array $e) => $e['day'] === $day && $e['when'] === $when)->values();

            if ($todays->count() <= $done) {
                break;
            }

            foreach ($todays->slice($done) as $event) {
                ($event['action'])();
                $done++;
            }
        }
    }

    private function markLessons(string $day): void
    {
        $lessons = Lesson::query()->whereIn('group_id', collect($this->groups)->pluck('id'))->whereDate('date', $day)->where('status', LessonStatus::Planned)->get();

        foreach ($lessons as $lesson) {
            $statuses = $this->attendance->rosterFor($lesson)
                ->mapWithKeys(function (Enrollment $enrollment) {
                    $roll = mt_rand(1, 100);

                    return [$enrollment->id => match (true) {
                        $roll <= 87 => AttendanceStatus::Present->value,
                        $roll <= 95 => AttendanceStatus::Absent->value,
                        default => AttendanceStatus::Excused->value,
                    }];
                })->all();

            if ($statuses === []) {
                continue;
            }

            $this->attendance->save($lesson, $statuses, $this->actor);
            $this->stats['lessons_held']++;
            $this->stats['attendance'] += count($statuses);
        }
    }

    private function pay(Enrollment $enrollment, float $share): void
    {
        $enrollment = $enrollment->fresh() ?? $enrollment;
        $price = (int) $enrollment->group->priceFor(CarbonImmutable::today())?->monthly_price;
        $due = $enrollment->discountedMonthlyPrice($price);
        $amount = (int) (round($due * $share / 10_000) * 10_000);

        if ($amount <= 0) {
            return;
        }

        $roll = mt_rand(1, 100);
        $cashbox = $this->cashboxes[match (true) {
            $roll <= 45 => 'naqd',
            $roll <= 70 => 'click',
            $roll <= 85 => 'payme',
            default => 'karta',
        }];

        $this->finance->receivePayment($enrollment, $cashbox->fresh(), $amount);
        $this->stats['payments']++;
        $this->stats['payments_sum'] += $amount;
    }

    private function expense(string $cashbox, ExpenseCategory $category, int $amount, string $note): void
    {
        $box = $this->cashboxes[$cashbox]->fresh();

        if ($box->balance < $amount) {
            $box = $this->cashboxes['naqd']->fresh();
        }

        if ($box->balance >= $amount) {
            $this->finance->expense($box, $category, $amount, $note);
            $this->stats['expenses_sum'] += $amount;
        }
    }

    private function at(CarbonImmutable $day, \Closure $action, string $when = 'morning'): void
    {
        $this->events[] = ['day' => $day->toDateString(), 'action' => $action, 'when' => $when];
    }

    private function account(string $name, Role $role, bool $staff): User
    {
        $user = User::query()->create(['name' => $name, 'phone' => $this->phone(), 'password' => Str::password(20)]);
        $user->assignRole($role->value);

        if ($staff) {
            $user->branches()->attach($this->branch->id);
        }

        return $user;
    }

    private function phone(): string
    {
        return self::PHONE_PREFIX.str_pad((string) ++$this->phoneSeq, 6, '0', STR_PAD_LEFT);
    }

    private function setNow(CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }
}
