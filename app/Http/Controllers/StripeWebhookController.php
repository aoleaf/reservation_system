<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentEventHandler;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Stripe の通知（webhook）。署名を確かめてから処理する。CSRF の対象外（bootstrap/app.php） */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $payments, PaymentEventHandler $handler): Response
    {
        try {
            $event = $payments->parseWebhook($request->getContent(), $request->header('Stripe-Signature'));
        } catch (Throwable $e) {
            Log::warning('webhook の署名が合いません', ['error' => $e->getMessage()]);

            return response('invalid', 400);
        }
        $handler->handle($event);

        return response('ok');
    }
}
