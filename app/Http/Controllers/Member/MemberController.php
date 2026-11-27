<?php

namespace App\Http\Controllers\Member;

use App\Domain\Booking\ScheduleWindow;
use App\Enums\ReservationKind;
use App\Enums\SlotStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\BookingPresenter;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Slot;
use App\Services\Booking\MonthlyService;
use App\Support\JaDate;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** 生徒さんの画面：マイページ（MemberIndexProps）・日程を選ぶ（MemberScheduleProps）・日程変更（MemberChangeProps） */
class MemberController extends Controller
{
    private const MONTH_NAMES = ['今月', '来月', '再来月'];

    public function index(Request $request): Response
    {
        $p = BookingPresenter::make();
        $member = $this->member($request);
        $thisMonth = JaDate::monthOf($p->today());
        $tomorrow = JaDate::addDays($p->today(), 1);

        $months = [];
        foreach ([0, 1, 2] as $i) {
            $month = JaDate::shiftMonth($thisMonth, $i);
            $mm = $member->monthOf($month);
            $window = ScheduleWindow::forMonth($month, $p->settings(), $p->today());
            $hasSlots = Slot::query()->where('date', 'like', $month.'-%')->exists();
            $sessions = $member->sessionsIn($month);
            // 再来月は、受付が始まっているか、もう日程があるときだけ出す
            if ($i === 2 && $sessions->isEmpty() && $mm->submitted_at === null && ($window['state'] === 'before' || ! $hasSlots)) {
                continue;
            }
            $rest = $mm->changeRemaining();
            $submitted = $mm->submitted_at !== null;
            $months[] = [
                'month' => $month,
                'label' => self::MONTH_NAMES[$i].'（'.JaDate::monthLabel($month).'）',
                'monthLabel' => JaDate::monthLabel($month),
                'submitted' => $submitted,
                'changeRemaining' => $rest,
                'canSchedule' => ! $submitted && $i >= 1 && $hasSlots && $window['state'] === 'open',
                'scheduleNote' => ! $submitted && $i >= 1 && $hasSlots ? $this->scheduleNote($window, $p) : null,
                'sessions' => $sessions->map(fn (Reservation $r) => [
                    'reservationId' => (string) $r->id,
                    'date' => $r->slot->dateString(),
                    'label' => $p->slotLabel($r->slot),
                    'past' => $r->slot->dateString() < $tomorrow,
                    'canChange' => $rest > 0 && $r->slot->dateString() >= $tomorrow,
                ])->all(),
            ];
        }

        return Inertia::render('member/index', [
            'member' => ['id' => (string) $member->id, 'name' => $member->name],
            'months' => $months,
            'sessionsPerMonth' => $p->settings()->sessions_per_month,
        ]);
    }

    public function schedule(Request $request, string $month): Response
    {
        $p = BookingPresenter::make();
        $member = $this->member($request);
        $month = JaDate::isMonth($month) ? $month : JaDate::shiftMonth(JaDate::monthOf($p->today()), 1);
        $window = ScheduleWindow::forMonth($month, $p->settings(), $p->today());

        $unavailable = null;
        if (! Slot::query()->where('date', 'like', $month.'-%')->exists()) {
            $unavailable = 'この月はまだ日程を選べません';
        } elseif ($member->monthOf($month)->submitted_at !== null) {
            $unavailable = 'この月の日程はもう提出済みです';
        } elseif ($window['state'] !== 'open') {
            $unavailable = $this->scheduleNote($window, $p);
        }

        return Inertia::render('member/schedule', [
            'month' => $month,
            'monthLabel' => JaDate::monthLabel($month),
            'unavailable' => $unavailable,
            'required' => $p->settings()->sessions_per_month,
            'leadingBlanks' => JaDate::weekdayIndex($month.'-01'),
            'days' => $this->pickDays($p, $month, fn () => $unavailable === null),
        ]);
    }

    public function submit(Request $request, string $month, MonthlyService $service): RedirectResponse
    {
        abort_unless(JaDate::isMonth($month), 404);
        $data = $request->validate(['slotIds' => ['required', 'array'], 'slotIds.*' => ['integer']], ['slotIds.*' => '日程を選んでください']);
        $service->submit($this->member($request), $month, $data['slotIds']);

        return redirect()->route('member.index');
    }

    public function change(Request $request, string $id): Response
    {
        $p = BookingPresenter::make();
        $member = $this->member($request);
        $r = ctype_digit($id) ? Reservation::query()->with('slot')->where('member_id', $member->id)->where('kind', ReservationKind::Monthly)->find($id) : null;
        if (! $r || ! $r->holdsSeat($p->now())) {
            return Inertia::render('member/change', ['reservation' => null, 'monthLabel' => '', 'changeRemaining' => 0, 'blockedReason' => null, 'leadingBlanks' => 0, 'days' => []]);
        }
        $from = $r->slot;
        $month = JaDate::monthOf($from->dateString());
        $rest = $member->monthOf($month)->changeRemaining();
        $blocked = null;
        if ($rest <= 0) {
            $blocked = 'この月の日程変更はもう使いました（月1回まで）';
        } elseif ($from->dateString() < JaDate::addDays($p->today(), 1)) {
            $blocked = '日程変更はレッスンの前日までです';
        }
        $otherDates = $member->sessionsIn($month)->reject(fn (Reservation $x) => $x->id === $r->id)->map(fn (Reservation $x) => $x->slot->dateString())->flip();

        return Inertia::render('member/change', [
            'reservation' => ['id' => (string) $r->id, 'label' => $p->slotLabel($from)],
            'monthLabel' => JaDate::monthLabel($month),
            'changeRemaining' => $rest,
            'blockedReason' => $blocked,
            'leadingBlanks' => JaDate::weekdayIndex($month.'-01'),
            'days' => $this->pickDays($p, $month, fn (Slot $s) => $blocked === null && $s->id !== $from->id && ! $otherDates->has($s->dateString())),
        ]);
    }

    public function changeStore(Request $request, Reservation $reservation, MonthlyService $service): RedirectResponse
    {
        $data = $request->validate(['toSlotId' => ['required', 'integer']], ['toSlotId.*' => '移す枠を選んでください']);
        $service->change($this->member($request), $reservation, $data['toSlotId']);

        return redirect()->route('member.index');
    }

    /** 日程を選ぶカレンダー（PickDay）。月謝の残席・お休み・前日まで・ok の条件で選べるかを決める */
    private function pickDays(BookingPresenter $p, string $month, Closure $ok): array
    {
        $dates = JaDate::datesInMonth($month);
        $byDate = $p->slots($dates[0], $dates[count($dates) - 1])->groupBy(fn (Slot $s) => $s->dateString());
        $tomorrow = JaDate::addDays($p->today(), 1);

        return array_map(function (string $date) use ($p, $byDate, $ok, $tomorrow) {
            $raw = $byDate[$date] ?? collect();
            $slots = $raw->map(fn (Slot $s) => $p->slotView($s, ReservationKind::Monthly, $date >= $tomorrow && $ok($s)))->values();

            return $p->calendarDay($date) + [
                'label' => JaDate::dayLabel($date),
                'closed' => $raw->isNotEmpty() && $raw->every(fn (Slot $s) => $s->status === SlotStatus::Closed),
                'slots' => $slots->all(),
                'selectable' => $slots->contains(fn ($s) => $s['bookable']),
            ];
        }, $dates);
    }

    /** 受付期間の案内 */
    private function scheduleNote(array $window, BookingPresenter $p): string
    {
        return match ($window['state']) {
            'before' => JaDate::dayLabel($window['opens']).'から選べます（締切 '.JaDate::dayLabel($window['closes']).'）',
            'open' => '締切：'.JaDate::dayLabel($window['closes']),
            default => '受付は'.JaDate::dayLabel($window['closes']).'で締め切りました。お店（'.$p->settings()->shop_phone.'）にご連絡ください',
        };
    }

    private function member(Request $request): Member
    {
        return $request->user()->member()->where('active', true)->firstOrFail();
    }
}
