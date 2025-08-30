<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Services\PaymentImportService;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request)
    {
        $payment_data = $request->validated();
        $service = new PaymentImportService();
        $response_data = $service->createPayment($payment_data);
        return isset($response_data['error']) ?
            response()->json(['success' => false, 'message' => $response_data['message'], 'errors' => [$response_data['error']]], 400) :
            response()->json(['success' => true, 'data' => $response_data['data']], 201);
    }
}
