<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\ContactFormat;
use Illuminate\Console\Command;

/** お店の電話番号を変える（キャンセルの案内・メールに出る）。管理画面には無いので、ここで変える */
class SetShopPhone extends Command
{
    protected $signature = 'settings:shop-phone {phone : 例 03-1234-5678}';

    protected $description = 'お店の電話番号を変える';

    public function handle(): int
    {
        $phone = ContactFormat::normalizePhone($this->argument('phone'));
        if (! ContactFormat::isPhone($phone)) {
            $this->error(ContactFormat::PHONE_MESSAGE);

            return self::FAILURE;
        }
        Setting::current()->update(['shop_phone' => $phone]);
        $this->info("お店の電話番号を {$phone} にしました");

        return self::SUCCESS;
    }
}
