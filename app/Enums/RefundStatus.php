<?php

namespace App\Enums;

/** 返金の結果 */
enum RefundStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    /** 返金に失敗し、スタッフが手で返金した（Stripe のダッシュボードなど） */
    case Manual = 'manual';
}
