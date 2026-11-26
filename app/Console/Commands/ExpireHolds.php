<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * 期限が切れた仮押さえ（pending）を expired にする。
 * 席の数え方はもともと期限内の pending だけなので、これは状態を正しくそろえるためのもの（Stripe の期限切れの通知が届かなかったときの保険）。
 */
class ExpireHolds extends Command
{
    protected $signature = 'reservations:expire-holds';

    protected $description = '期限が切れた仮押さえを戻す';

    public function handle(): int
    {
        $n = Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->where('expires_at', '<=', CarbonImmutable::now())
            ->update(['status' => ReservationStatus::Expired]);
        if ($n > 0) {
            $this->info("期限切れの仮押さえを {$n} 件戻しました");
        }

        return self::SUCCESS;
    }
}
