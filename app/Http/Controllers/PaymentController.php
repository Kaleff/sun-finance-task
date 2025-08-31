<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Services\PaymentService;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request)
    {
        $payment_data = $request->validated();
        $service = new PaymentService();
        $response_data = $service->createPayment($payment_data);
        return isset($response_data['error']) ?
            response()->json(['success' => false, 'message' => $response_data['message'], 'errors' => [$response_data['error']]], 400) :
            response()->json(['success' => true, 'message' => $response_data['message'], 'data' => $response_data['data']], 201);
    }
}
