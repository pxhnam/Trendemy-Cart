<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Interfaces\MomoServiceInterface;
use App\Services\Interfaces\VNPayServiceInterface;

class TransactionController extends Controller
{
    public function __construct(
        private VNPayServiceInterface $vnpayService,
        private MomoServiceInterface $momoService
    ) {
    }


    public function vnpayReturn(Request $request)
    {
        if (!empty($request->all())) {
            $data = $this->vnpayService->response($request);
            return $this->handleResponse($data);
        } else {
            return redirect()->route('home');
        }
    }

    public function momoReturn(Request $request)
    {
        if (!empty($request->all())) {
            $data = $this->momoService->response($request);
            return $this->handleResponse($data);
        } else {
            return redirect()->route('home');
        }
    }
    public function handleResponse($data)
    {
        return redirect()->route('orders.result')->with([
            'result' => $data
        ]);
    }
}
