<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Order;
use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use App\Services\Interfaces\BankServiceInterface;
use App\Services\Interfaces\MomoServiceInterface;
use App\Services\Interfaces\OrderServiceInterface;
use App\Services\Interfaces\VNPayServiceInterface;

class OrderController extends Controller
{

    public function __construct(
        private OrderServiceInterface $orderService,
        private VNPayServiceInterface $vnpayService,
        private MomoServiceInterface $momoService,
        private BankServiceInterface $bankService
    ) {
    }

    public function index()
    {
        $orderInfo = $this->orderService->show();
        if (count($orderInfo['carts']) == 0) {
            Session::forget(['carts', 'codes']);
            return redirect()->route('home');
        }
        return view('client.home.checkout', $orderInfo);
    }

    public function checkout(Request $request)
    {
        try {
            $method = $request->method;
            if ($method) {
                $order = $this->orderService->createOrder();
                if ($order) {
                    $request->merge($order);
                    switch ($method) {
                        case PaymentMethod::VNPAY:
                            $vnp_Url = $this->vnpayService->create($request);
                            return redirect($vnp_Url);
                        case PaymentMethod::MOMO:
                            $payUrl = $this->momoService->create($request);
                            return redirect($payUrl);
                        case PaymentMethod::BANK:
                            return $this->bankService->create($request);
                        default:
                            return redirect()->back()->withErrors(['method' => 'Không rõ phương thức thanh toán.']);
                    }
                } else {
                    return redirect()->back()->with(
                        [
                            'notify' =>
                            [
                                'type' => 'error',
                                'message' => 'Có lỗi xảy ra! Vui lòng thử lại sau.'
                            ]
                        ]
                    );
                }
            } else {
                return redirect()->back()->withErrors(['method' => 'Vui lòng chọn phương thức thanh toán.']);
            }
        } catch (Exception $ex) {
            Log::info($ex->getMessage());
        }
    }
    public function checkBank()
    {
        return $this->bankService->checkBank();
    }
    public function result()
    {
        try {
            $result = session('result');
            if (isset($result)) {
                if ($result)
                    $this->orderService->sendInvoiceMail($result);
                session()->forget('result');
                return view('client.home.result', compact('result'));
            }
        } catch (Exception $ex) {
            Log::error('[' . __METHOD__ . ']: ' . $ex->getMessage());
        }
        return redirect()->route('home');
    }

    public function showInvoice($code)
    {
        try {
            set_time_limit(180);
            $order = $this->orderService->invoicebyCode($code);
            if ($order) {
                $order['customer'] = auth()->user()->name;
                $pdf = PDF::loadView('pdf.invoice', ['order' => $order]);
                return $pdf->stream();
            }
        } catch (Exception $ex) {
            Log::error('[' . __METHOD__ . ']: ' . $ex->getMessage());
        }
        abort(404);
    }
}
