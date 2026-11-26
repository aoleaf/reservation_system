<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationKind;
use App\Enums\SlotStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\BookingPresenter;
use App\Models\Slot;
use App\Services\Booking\SlotService;
use App\Support\JaDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** カレンダー（/admin/slots?month&date）と枠の状態の切替。props は AdminSlotsProps */
class SlotController extends Controller
{
    public function index(Request $request): Response
    {
        $p = BookingPresenter::make();
        $month = JaDate::isMonth($request->query('month')) ? $request->query('month') : JaDate::monthOf($p->today());
        $next = JaDate::shiftMonth($month, 1);
        $nextDates = JaDate::datesInMonth($next);
        $byDate = $p->slots($month.'-01', $nextDates[count($nextDates) - 1])->groupBy(fn (Slot $s) => $s->dateString());

        $months = array_map(function (string $m) use ($p, $byDate) {
            $dates = JaDate::datesInMonth($m);

            return [
                'month' => $m,
                'label' => JaDate::monthLabel($m),
                'leadingBlanks' => JaDate::weekdayIndex($dates[0]),
                'days' => array_map(function (string $date) use ($p, $byDate) {
                    $slots = ($byDate[$date] ?? collect())->map(fn (Slot $s) => $p->adminSlotCell($s))->values();

                    return $p->calendarDay($date) + [
                        'label' => JaDate::dayLabel($date),
                        'closed' => $slots->isNotEmpty() && $slots->every(fn ($s) => $s['status'] === SlotStatus::Closed->value),
                        'slots' => $slots->all(),
                    ];
                }, $dates),
            ];
        }, [$month, $next]);

        return Inertia::render('admin/slots', [
            'month' => $month,
            'prevMonth' => JaDate::shiftMonth($month, -1),
            'nextMonth' => $next,
            'months' => $months,
            'selected' => $this->selected($request->query('date'), $p),
        ]);
    }

    /** パネルに出す日（?date=）。2か月の外の日でも出せるよう、その日だけ読み直す */
    private function selected(mixed $date, BookingPresenter $p): ?array
    {
        if (! JaDate::isDate($date)) {
            return null;
        }
        $slots = $p->slots($date, $date);

        return [
            'date' => $date,
            'label' => JaDate::dayLabel($date),
            'slots' => $slots->map(fn (Slot $s) => $p->adminSlotCell($s) + ['entries' => $p->entries($s)])->all(),
            'dayAction' => $slots->contains(fn (Slot $s) => $s->status === SlotStatus::Open) ? 'pause'
                : ($slots->contains(fn (Slot $s) => $s->status === SlotStatus::Paused) ? 'resume' : null),
        ];
    }

    /** 枠の状態を切り替える（お休みは予約が無い枠だけ） */
    public function update(Request $request, Slot $slot, SlotService $service): RedirectResponse
    {
        $data = $request->validate(
            ['status' => ['required', Rule::enum(SlotStatus::class)]],
            ['status.*' => '枠の状態を選んでください'],
        );
        $service->setStatus($slot, SlotStatus::from($data['status']));

        return back();
    }

    /** 1日分をまとめて受付停止／再開する（お休みの枠はそのまま） */
    public function updateDay(Request $request, string $date, SlotService $service): RedirectResponse
    {
        abort_unless(JaDate::isDate($date), 404);
        $data = $request->validate(
            ['status' => ['required', Rule::in([SlotStatus::Open->value, SlotStatus::Paused->value])]],
            ['status.*' => '「受付停止」か「再開」を選んでください'],
        );
        $service->setDayStatus($date, SlotStatus::from($data['status']));

        return back();
    }
}
