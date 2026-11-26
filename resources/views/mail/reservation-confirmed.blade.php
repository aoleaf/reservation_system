{{ $reservation->contact_name }} 様

体験レッスンのご予約をお受けしました。

予約番号：{{ $reservation->code }}
日時：{{ $slotLabel }}
人数：{{ $reservation->party_size }}名
お支払い：{{ number_format($reservation->amount) }}円（お支払い済み）

当日は受付で予約番号をお伝えください。

■ キャンセルするときは
下のリンクから、またはお電話（{{ $shopPhone }}）で承ります。
{{ $cancelUrl }}

@foreach ($cancelPolicy as $p)
・{{ $p }}
@endforeach

このリンクは、ご予約の方だけが使えます。ほかの方に教えないでください。

つちのね
