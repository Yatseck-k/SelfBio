<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Player;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Whodoku\Application\Payment\FakeGateway;
use Whodoku\Http\Controllers\Controller;

/** The payment page of the stand-in provider (development only): confirm or cancel by hand. */
class FakePaymentController extends Controller
{
    public function __construct(private readonly FakeGateway $gateway)
    {
        abort_if(config('killdoku.shop.driver') !== 'fake' || (app()->isProduction() && !config('killdoku.shop.alpha')), 404);
    }

    public function show(string $payment): View
    {
        $data = $this->gateway->payment($payment);
        abort_if($data === null, 404);

        return view('shop.fake', ['id' => $payment, 'payment' => $data]);
    }

    public function decide(Request $request, string $payment): RedirectResponse
    {
        $data = $this->gateway->payment($payment);
        abort_if($data === null, 404);
        if ($data['status'] === 'pending') {
            $this->gateway->update($payment, ['status' => $request->input('result') === 'pay' ? 'succeeded' : 'canceled']);
        }

        return redirect($data['return']);
    }
}
