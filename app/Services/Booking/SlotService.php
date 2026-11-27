<?php

namespace App\Services\Booking;

use App\Enums\SlotStatus;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** 枠の状態（営業・受付停止・お休み）の切替。mocks/actions.ts の setSlotStatus・setDayStatus に当たる */
class SlotService
{
    /** お休みにできるのは、席を使っている予約が無い枠だけ */
    public function setStatus(Slot $slot, SlotStatus $status): void
    {
        DB::transaction(function () use ($slot, $status) {
            $locked = Slot::query()->lockForUpdate()->findOrFail($slot->id);
            if ($status === SlotStatus::Closed && $locked->hasHoldingReservations(CarbonImmutable::now())) {
                throw ValidationException::withMessages(['slotId' => '予約が入っている枠はお休みにできません。受付停止にして、キャンセルを入力してからお休みにしてください']);
            }
            $locked->update(['status' => $status]);
        });
    }

    /** 1日分をまとめて受付停止／再開する。お休みの枠はそのまま */
    public function setDayStatus(string $date, SlotStatus $status): void
    {
        $slots = Slot::query()->where('date', $date);
        if (! $slots->exists()) {
            throw ValidationException::withMessages(['date' => 'この日は枠がありません']);
        }
        Slot::query()->where('date', $date)->where('status', '!=', SlotStatus::Closed)->update(['status' => $status]);
    }
}
