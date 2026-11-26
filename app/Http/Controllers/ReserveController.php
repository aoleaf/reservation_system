<?php

namespace App\Http\Controllers;

use App\Enums\ReservationKind;
use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use App\Http\Presenters\BookingPresenter;
use App\Models\Reservation;
use App\Models\Slot;
use App\Rules\EmailAddress;
use App\Rules\PhoneNumber;
use App\Services\Booking\WebReservationService;
use App\Support\ContactFormat;
use App\Support\JaDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** お客様の体験予約：一覧（ReserveIndexProps）・入力・確認・支払いへ・完了・払わずに戻ったとき */
class ReserveController extends Controller
{
    /** △（残りわずか）にする、その日の体験の残席の合計 */
    private const FEW_SEATS = 3;

    public function index(Request $request): Response
    {
        $p = BookingPresenter::make();
        $from = JaDate::addDays($p->today(), 1); // 前日まで受付
        $firstMonth = JaDate::monthOf($from);
        $lastDate = Slot::query()->max('date');
        $lastMonth = $lastDate ? JaDate::monthOf($lastDate) : $firstMonth;
        $wanted = JaDate::isMonth($request->query('month')) ? $request->query('month') : $firstMonth;
        $month = max($firstMonth, min($lastMonth, $wanted));
        $dates = JaDate::datesInMonth($month);
        $byDate = $p->slots($dates[0], $dates[count($dates) - 1])->groupBy(fn (Slot $s) => $s->dateString());

        $days = array_map(function (string $date) use ($p, $byDate, $from) {
            $raw = $byDate[$date] ?? collect();
            // 受付停止・お休みだけの日は「受付なし（−）」と見せる（満席の × とは分ける）
            $open = $date >= $from && $raw->contains(fn (Slot $s) => $s->trial_open && $s->status === SlotStatus::Open);
            $slots = $open ? $raw->map(fn (Slot $s) => $p->slotView($s, ReservationKind::Trial))->values()->all() : [];
            $rest = array_sum(array_map(fn ($s) => $s['bookable'] ? $s['remaining'] : 0, $slots));
            $mark = ! $open ? 'none' : ($rest === 0 ? 'full' : ($rest <= self::FEW_SEATS ? 'few' : 'ok'));

            return $p->calendarDay($date) + ['label' => JaDate::dayLabel($date), 'mark' => $mark, 'slots' => $slots];
        }, $dates);

        return Inertia::render('reserve/index', [
            'unitPrice' => $p->settings()->trial_price,
            'note' => '体験レッスンは土曜・日曜のみ。ご予約は前日まで受け付けています。',
            'notice' => $request->session()->get('reserve_notice'),
            'month' => $month,
            'monthLabel' => JaDate::monthLabel($month),
            'prevMonth' => $month > $firstMonth ? JaDate::shiftMonth($month, -1) : null,
            'nextMonth' => $month < $lastMonth ? JaDate::shiftMonth($month, 1) : null,
            'leadingBlanks' => JaDate::weekdayIndex($dates[0]),
            'days' => $days,
            'initialDate' => collect($days)->first(fn ($d) => in_array($d['mark'], ['ok', 'few'], true))['date'] ?? null,
        ]);
    }

    public function form(Request $request): Response
    {
        $p = BookingPresenter::make();
        $slot = $this->trialSlot($request->query('slot'), $p);
        $view = $slot ? $p->slotView($slot, ReservationKind::Trial) : null;
        $max = $view['remaining'] ?? 0;
        $price = $p->settings()->trial_price;

        return Inertia::render('reserve/form', [
            'slot' => $view,
            'slotLabel' => $slot ? $p->slotLabel($slot) : '',
            'unitPrice' => $price,
            'maxParty' => $max,
            'options' => array_map(fn ($n) => ['n' => $n, 'amount' => $price * $n], $max > 0 ? range(1, $max) : []),
            'initial' => [
                'partySize' => min(self::partySize($request->query('n')), max(1, $max)),
                'contact' => $this->contact($request),
            ],
        ]);
    }

    public function confirm(Request $request): Response
    {
        $p = BookingPresenter::make();
        $slot = $this->trialSlot($request->query('slot'), $p);
        $n = self::partySize($request->query('n'));
        $contact = $this->contact($request);
        $contactError = match (true) {
            trim($contact['name']) === '' || $contact['phone'] === '' || $contact['email'] === '' => 'お名前・電話番号・メールアドレスを入力してください',
            ! ContactFormat::isPhone($contact['phone']) => ContactFormat::PHONE_MESSAGE,
            ! ContactFormat::isEmail($contact['email']) => ContactFormat::EMAIL_MESSAGE,
            default => null,
        };

        return Inertia::render('reserve/confirm', [
            'slot' => $slot ? $p->slotView($slot, ReservationKind::Trial) : null,
            'slotLabel' => $slot ? $p->slotLabel($slot) : '',
            'partySize' => $n,
            'contact' => $contact,
            'unitPrice' => $p->settings()->trial_price,
            'amount' => $p->settings()->trial_price * $n,
            'cancelPolicy' => $p->cancelPolicy(),
            'cancelGuide' => $p->cancelGuide(),
            'contactError' => $contactError,
        ]);
    }

    /** 「支払う」：仮押さえを作り、決済のページへ移る */
    public function store(Request $request, WebReservationService $service): HttpResponse
    {
        $this->normalizeContact($request);
        $data = $request->validate([
            'slotId' => ['required', 'integer'],
            'partySize' => ['required', 'integer', 'min:1', 'max:20'],
            'contact.name' => ['required', 'string', 'max:100'],
            'contact.phone' => ['required', 'string', 'max:32', new PhoneNumber],
            'contact.email' => ['required', 'string', new EmailAddress],
        ], [
            'slotId.*' => '枠を選んでください',
            'partySize.*' => '人数を選んでください',
            'contact.name.*' => 'お名前を入力してください',
            'contact.phone.required' => '電話番号を入力してください',
            'contact.email.required' => 'メールアドレスを入力してください',
        ]);

        return Inertia::location($service->start($data));
    }

    /** 決済から戻る（署名付きの URL でだけ、予約の中身を見せる） */
    public function complete(Request $request, string $code): Response
    {
        $p = BookingPresenter::make();
        $r = $request->hasValidSignature() ? Reservation::query()->with('slot')->where('code', $code)->where('kind', ReservationKind::Trial)->first() : null;

        return Inertia::render('reserve/complete', [
            'reservation' => $r ? ['id' => $r->code, 'partySize' => $r->party_size, 'amount' => $r->amount, 'contact' => ['name' => $r->contact_name, 'phone' => $r->contact_phone, 'email' => $r->contact_email]] : null,
            'paymentStatus' => match (true) {
                $r === null => null,
                $r->status === ReservationStatus::Confirmed => 'confirmed',
                $r->status === ReservationStatus::Pending && $r->holdsSeat($p->now()) => 'pending',
                default => 'expired',
            },
            'slotLabel' => $r ? $p->slotLabel($r->slot) : '',
            'cancelPolicy' => $p->cancelPolicy(),
            'cancelGuide' => $p->cancelGuide(),
        ]);
    }

    /** 決済のページで「戻る」を押したとき：席を戻して一覧へ */
    public function abandon(Request $request, string $code, WebReservationService $service): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $r = Reservation::query()->where('code', $code)->first();
        if ($r) {
            $service->abandon($r);
        }

        return redirect()->route('reserve.index')->with('reserve_notice', 'お支払いを中止しました。席の仮押さえを解除しました。');
    }

    /** 体験で選べる枠か（存在し、土日で、前日まで） */
    private function trialSlot(mixed $id, BookingPresenter $p): ?Slot
    {
        if (! is_string($id) || ! ctype_digit($id)) {
            return null;
        }
        $slot = Slot::query()->with(['reservations' => fn ($q) => $q->holdingSeat($p->now())])->find((int) $id);

        return $slot && $slot->trial_open && $slot->dateString() >= JaDate::addDays($p->today(), 1) ? $slot : null;
    }

    /** n が数字でない・1 未満なら 1 に丸める */
    private static function partySize(mixed $v): int
    {
        $n = is_numeric($v) ? (int) floor((float) $v) : 0;

        return $n >= 1 ? $n : 1;
    }

    /** @return array{name: string, phone: string, email: string} */
    private function contact(Request $request): array
    {
        $s = fn (string $k) => is_string($request->query($k)) ? $request->query($k) : '';

        return ['name' => $s('name'), 'phone' => ContactFormat::normalizePhone($s('phone')), 'email' => ContactFormat::normalizeEmail($s('email'))];
    }

    /** 送られてきた連絡先をそろえる（全角を半角に、メールは小文字に）。確かめる前に行う */
    private function normalizeContact(Request $request): void
    {
        $c = $request->input('contact');
        if (is_array($c)) {
            $request->merge(['contact' => [
                'name' => is_string($c['name'] ?? null) ? trim($c['name']) : ($c['name'] ?? null),
                'phone' => is_string($c['phone'] ?? null) ? ContactFormat::normalizePhone($c['phone']) : ($c['phone'] ?? null),
                'email' => is_string($c['email'] ?? null) ? ContactFormat::normalizeEmail($c['email']) : ($c['email'] ?? null),
            ]]);
        }
    }
}
