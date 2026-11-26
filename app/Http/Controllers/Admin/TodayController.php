<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\Seats;
use App\Enums\ReservationKind;
use App\Enums\RefundStatus;
use App\Enums\SlotStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\BookingPresenter;
use App\Models\Reservation;
use App\Models\Slot;
use App\Support\JaDate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** 予約一覧（/admin?date）。props は types/pages.ts の AdminTodayProps */
class TodayController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $p = BookingPresenter::make();
        $date = JaDate::isDate($request->query('date')) ? $request->query('date') : $p->today();
        $slots = $p->slots($date, $date);

        return Inertia::render('admin/today', [
            'date' => $date,
            'label' => JaDate::dayLabel($date),
            'isToday' => $date === $p->today(),
            'prevDate' => JaDate::addDays($date, -1),
            'nextDate' => JaDate::addDays($date, 1),
            'closed' => $slots->isNotEmpty() && $slots->every(fn (Slot $s) => $s->status === SlotStatus::Closed),
            'refundIssues' => $this->refundIssues($p),
            'slots' => $slots->map(fn (Slot $slot) => [
                'slot' => $p->slotView($slot, ReservationKind::Trial),
                'add' => $this->addLink($slot, $p),
                'entries' => $p->entries($slot),
            ])->all(),
        ]);
    }

    /** この枠に予約を入れるリンク：体験が入れば体験、平日など体験が無理なら月謝、どちらも無理なら null */
    private function addLink(Slot $slot, BookingPresenter $p): ?array
    {
        if ($slot->status !== SlotStatus::Open) {
            return null;
        }
        foreach ([ReservationKind::Trial, ReservationKind::Monthly] as $kind) {
            $rest = Seats::remaining($slot, $slot->reservations, $kind, $p->now());
            if ($rest > 0) {
                return ['kind' => $kind->value, 'remaining' => $rest];
            }
        }

        return null;
    }

    /** 返金に失敗した予約（日付を問わず）。スタッフが手で返金して「返金した」を押すまで出す */
    private function refundIssues(BookingPresenter $p): array
    {
        return Reservation::query()
            ->where('refund_status', RefundStatus::Failed)
            ->with('slot')
            ->orderBy('canceled_at')
            ->get()
            ->map(fn (Reservation $r) => [
                'id' => (string) $r->id,
                'code' => $r->code,
                'name' => (string) $r->contact_name,
                'phone' => $r->contact_phone,
                'slotLabel' => $p->slotLabel($r->slot),
                'amount' => (int) $r->refunded_amount,
            ])
            ->all();
    }
}
