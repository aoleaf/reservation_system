<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CancelReason;
use App\Enums\RefundStatus;
use App\Enums\ReservationKind;
use App\Enums\SlotStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\BookingPresenter;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Slot;
use App\Rules\PhoneNumber;
use App\Services\Booking\CancellationService;
use App\Services\Booking\StaffReservationService;
use App\Support\ContactFormat;
use App\Support\JaDate;
use App\Support\MemberNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** スタッフの予約入力（AdminReservationNewProps）・登録・キャンセル */
class ReservationController extends Controller
{
    public function create(Request $request): Response
    {
        $p = BookingPresenter::make();
        $kind = $request->query('kind') === 'monthly' ? ReservationKind::Monthly : ReservationKind::Trial;
        $date = JaDate::isDate($request->query('date')) ? $request->query('date') : $p->today();
        $month = JaDate::monthOf($date);
        $raw = $p->slots($date, $date);
        $slots = $raw->map(fn (Slot $s) => $p->slotView($s, $kind))->values();

        // 選べる日：今日から、お休みでない日（体験は土日だけ）。体験は12日分、月謝は21日分
        $dateOptions = Slot::query()
            ->where('date', '>=', $p->today())
            ->where('status', '!=', SlotStatus::Closed)
            ->when($kind === ReservationKind::Trial, fn ($q) => $q->where('trial_open', true))
            ->orderBy('date')->distinct()->limit($kind === ReservationKind::Monthly ? 21 : 12)
            ->pluck('date')
            ->map(fn ($d) => ['date' => $d, 'label' => JaDate::dayLabel($d)])->all();

        $dateNote = null;
        if ($raw->isEmpty()) {
            $dateNote = 'この日は枠がありません';
        } elseif ($raw->every(fn (Slot $s) => $s->status === SlotStatus::Closed)) {
            $dateNote = 'この日はお休みです';
        } elseif ($kind === ReservationKind::Trial && ! $raw->contains(fn (Slot $s) => $s->trial_open)) {
            $dateNote = '体験レッスンは土曜・日曜だけです。上から日を選んでください';
        }

        $members = $kind === ReservationKind::Monthly
            ? Member::query()->where('active', true)->get()->sortBy(fn (Member $m) => MemberNumber::value($m->member_no))->values()->map(function (Member $m) use ($month) {
                $sessions = $m->sessionsIn($month);

                return ['id' => (string) $m->id, 'memberNo' => $m->member_no, 'name' => $m->name, 'count' => $sessions->count(), 'dates' => $sessions->map(fn (Reservation $r) => $r->slot->dateString())->all()];
            })->all()
            : [];
        $wanted = $slots->first(fn ($s) => $s['id'] === (string) $request->query('slotId', $request->query('slot')) && $s['bookable']);
        $memberParam = (string) $request->query('member', '');

        return Inertia::render('admin/reservation-new', [
            'kind' => $kind->value,
            'date' => $date,
            'label' => JaDate::dayLabel($date),
            'dateNote' => $dateNote,
            'monthLabel' => JaDate::monthLabel($month),
            'required' => $p->settings()->sessions_per_month,
            'members' => $members,
            'initialMemberId' => collect($members)->firstWhere('id', $memberParam)['id'] ?? null,
            'dateOptions' => $dateOptions,
            'slots' => $slots->all(),
            'initialSlotId' => $wanted['id'] ?? null,
            'channels' => [
                ['value' => 'phone', 'label' => '電話'],
                ['value' => 'dm', 'label' => 'DM'],
                ['value' => 'walkin', 'label' => '来店（口頭）'],
            ],
        ]);
    }

    public function store(Request $request, StaffReservationService $service): RedirectResponse
    {
        // 電話番号は全角を半角にそろえてから確かめる（空でもよい）
        $c = $request->input('contact');
        if (is_array($c) && is_string($c['phone'] ?? null)) {
            $request->merge(['contact' => ['phone' => ContactFormat::normalizePhone($c['phone']) ?: null] + $c]);
        }
        $data = $request->validate([
            'kind' => ['required', Rule::enum(ReservationKind::class)],
            'slotId' => ['required', 'integer'],
            'channel' => ['required', Rule::in(['phone', 'dm', 'walkin', 'staff'])],
            'partySize' => ['exclude_unless:kind,trial', 'required', 'integer', 'min:1', 'max:20'],
            'contact.name' => ['exclude_unless:kind,trial', 'required', 'string', 'max:100'],
            'contact.phone' => ['exclude_unless:kind,trial', 'nullable', 'string', 'max:32', new PhoneNumber],
            'memberId' => ['exclude_unless:kind,monthly', 'required', 'integer'],
        ], [
            'slotId.*' => '枠を選んでください',
            'partySize.*' => '人数を選んでください',
            'contact.name.*' => 'お名前を入力してください',
            'memberId.*' => '生徒さんを選んでください',
            'kind.*' => '体験か月謝かを選んでください',
            'channel.*' => '経路を選んでください',
        ]);

        $reservation = $service->create($data, $request->user());

        return redirect()->route('admin.today', ['date' => $reservation->slot->dateString()]);
    }

    public function cancel(Request $request, Reservation $reservation, CancellationService $service): RedirectResponse
    {
        $data = $request->validate(
            ['reason' => ['required', Rule::enum(CancelReason::class)]],
            ['reason.*' => 'お客様の都合か、お店の都合かを選んでください'],
        );
        $service->cancelByStaff($reservation, CancelReason::from($data['reason']));

        return back();
    }

    /** 返金に失敗した予約を、手で返金したことにする */
    public function refundResolved(Reservation $reservation): RedirectResponse
    {
        if ($reservation->refund_status !== RefundStatus::Failed) {
            throw \Illuminate\Validation\ValidationException::withMessages(['reservationId' => 'この予約は返金の失敗ではありません']);
        }
        $reservation->update(['refund_status' => RefundStatus::Manual]);

        return back();
    }
}
