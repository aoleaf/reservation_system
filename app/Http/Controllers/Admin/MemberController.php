<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Presenters\BookingPresenter;
use App\Models\Member;
use App\Models\MemberMonth;
use App\Models\Reservation;
use App\Support\JaDate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** 生徒一覧（AdminMembersProps）と生徒の詳細（AdminMemberShowProps） */
class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $p = BookingPresenter::make();
        $month = JaDate::shiftMonth(JaDate::monthOf($p->today()), 1);
        $filter = $request->query('filter') === 'unsubmitted' ? 'unsubmitted' : 'all';
        $submitted = MemberMonth::query()->where('month', $month)->whereNotNull('submitted_at')->pluck('member_id')->flip();

        $all = Member::query()->where('active', true)->orderBy('id')->get()
            ->map(fn (Member $m) => ['id' => (string) $m->id, 'name' => $m->name, 'phone' => $m->phone, 'submitted' => $submitted->has($m->id)]);
        $unsubmitted = $all->reject(fn ($m) => $m['submitted'])->values();

        return Inertia::render('admin/members', [
            'month' => $month,
            'monthLabel' => JaDate::monthLabel($month),
            'filter' => $filter,
            'counts' => ['all' => $all->count(), 'unsubmitted' => $unsubmitted->count()],
            'members' => $filter === 'unsubmitted' ? $unsubmitted->all() : $all->all(),
        ]);
    }

    public function show(string $id): Response
    {
        $p = BookingPresenter::make();
        $m = ctype_digit($id) ? Member::query()->find($id) : null;
        $thisMonth = JaDate::monthOf($p->today());

        return Inertia::render('admin/member-show', [
            'member' => $m ? ['id' => (string) $m->id, 'name' => $m->name, 'phone' => $m->phone, 'email' => $m->email] : null,
            'months' => ! $m ? [] : array_map(function (string $month) use ($m, $p) {
                $mm = $m->monthOf($month);

                return [
                    'month' => $month,
                    'label' => JaDate::monthLabel($month),
                    'submitted' => $mm->submitted_at !== null,
                    'changeRemaining' => $mm->changeRemaining(),
                    'sessions' => $m->sessionsIn($month)->map(fn (Reservation $r) => $p->slotLabel($r->slot))->all(),
                ];
            }, [$thisMonth, JaDate::shiftMonth($thisMonth, 1)]),
        ]);
    }
}
