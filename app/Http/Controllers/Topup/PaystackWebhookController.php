<?php

namespace App\Http\Controllers\Topup;

use App\Http\Controllers\Controller;
use App\Models\WalletFunding;
use App\Services\Payments\PaystackGateway;
use App\Services\Topup\FundingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Paystack → charge.success for wallet fundings. The signature is checked; the payment is then verified with Paystack. */
class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackGateway $paystack, FundingService $funding)
    {
        if (!$paystack->validSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            Log::warning('Paystack webhook with a bad signature', ['ip' => $request->ip()]);

            return response()->json(['ok' => false], 401);
        }

        if ($request->input('event') === 'charge.success') {
            $ref = (string) $request->input('data.reference');
            if ($ref !== '' && WalletFunding::where('reference', $ref)->exists()) {
                $funding->confirm($ref);   // verify with Paystack; never trust the webhook body alone
            }
        }

        return response()->json(['ok' => true]);
    }
}
