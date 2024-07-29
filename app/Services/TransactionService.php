<?php

namespace App\Services;

use App\Enums\CartState;
use App\Enums\OrderState;
use App\Enums\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\Interfaces\TransactionServiceInterface;
use App\Repositories\Interfaces\TransactionRepositoryInterface;

class TransactionService implements TransactionServiceInterface
{

    public function __construct(
        protected TransactionRepositoryInterface $transactionRepository,
        protected OrderRepositoryInterface $orderRepository,
        protected CouponRepositoryInterface $couponRepository,
        protected CartRepositoryInterface $cartRepository
    ) {
    }

    public function create($request, $method)
    {
        list($orderId, $statusCode) = $this->getOrderInfo($request, $method);
        $response = $request->all();

        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            return false;
        }
        DB::beginTransaction();
        try {
            $this->createTransaction($order->id, $method, $response);

            if ($statusCode) {
                $this->updateOrderAndCarts($order);
                $this->updateCoupons();
                Session::forget(['carts', 'codes']);
            } else {
                $order->state = OrderState::FAILED;
                $order->save();
            }
            DB::commit();
            return $statusCode ? $order->id : false;
        } catch (\Exception $ex) {
            DB::rollback();
            Log::error('VNPay transaction failed: ' . $ex->getMessage());
            return false;
        }
    }

    protected function getOrderInfo($request, $method)
    {
        $orderId = null;
        $statusCode = false;

        switch ($method) {
            case PaymentMethod::VNPAY:
                $orderId = $request->vnp_OrderInfo;
                $statusCode = $request->vnp_ResponseCode === '00';
                break;
            case PaymentMethod::MOMO:
                $orderId = $request->requestId;
                $statusCode = $request->resultCode === '0';
                break;
            case PaymentMethod::BANK:
                $orderId = $request['orderCode'];
                $statusCode = $request['status'] === 'PAID';
                break;
            default:
                break;
        }

        return [$orderId, $statusCode];
    }


    protected function createTransaction($orderId, $method, $response)
    {
        $this->transactionRepository->create([
            'user_id' => Auth::user()->id,
            'payment_method' => $method,
            'order_id' => $orderId,
            'response' => json_encode($response)
        ]);
    }

    protected function updateOrderAndCarts($order)
    {
        $order->state = OrderState::PAID;
        $order->save();

        $ids = Session::get('carts', []);
        foreach ($ids as $id) {
            $cart = $this->cartRepository->findById($id);
            if ($cart) {
                $cart->state = CartState::PURCHASED;
                $cart->save();
            }
        }
    }

    protected function updateCoupons()
    {
        if (!Session::has('codes')) {
            return;
        }

        $codes = Session::get('codes');
        foreach ($codes as $code) {
            $coupon = $this->couponRepository->findByCode($code);
            if ($coupon) {
                $coupon->usage_count++;
                $coupon->save();
            }
        }
    }
}
