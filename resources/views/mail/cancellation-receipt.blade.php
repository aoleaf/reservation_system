{{ $reservation->contact_name }} 様

体験レッスンのご予約のキャンセルを承りました。

予約番号：{{ $reservation->code }}
日時：{{ $slotLabel }}
@if (($reservation->refunded_amount ?? 0) > 0)
返金額：{{ number_format($reservation->refunded_amount) }}円
（カード会社により、ご利用明細への反映に数日〜数週間かかることがあります）
@else
返金：ありません
@endif

またのご予約をお待ちしております。ご不明な点はお電話（{{ $shopPhone }}）でお問い合わせください。

つちのね
