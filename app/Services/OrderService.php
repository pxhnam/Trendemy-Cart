<?php

namespace App\Services;

use Exception;
use Carbon\Carbon;
use App\Jobs\SendInvoiceMail;
use Illuminate\Support\Str;
use App\Enums\PaymentMethod;
use App\Helpers\NumberFormat;
use App\Traits\DiscountTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Services\Interfaces\OrderServiceInterface;
use App\Services\Interfaces\ConfigServiceInterface;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use App\Repositories\Interfaces\CourseRepositoryInterface;
use App\Repositories\Interfaces\OrderDetailRepositoryInterface;

class OrderService implements OrderServiceInterface
{
    use DiscountTrait;

    private $userId;

    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected OrderDetailRepositoryInterface $orderDetailRepository,
        protected CartRepositoryInterface $cartRepository,
        protected CourseRepositoryInterface $courseRepository,
        protected CouponRepositoryInterface $couponRepository,
        protected ConfigServiceInterface $configService
    ) {

        $this->userId = Auth::user()->id;
    }

    public function show()
    {
        try {
            $ids = Session::get('carts') ?? [];
            $codes = Session::get('codes') ?? [];
            list($carts, $base, $total) = $this->getInfoCourseByCart($ids);
            $carts = array_map(function ($cart) {
                $cart['cost'] = NumberFormat::VND($cart['cost']);
                return $cart;
            }, $carts);
            $discount = $this->makeDiscount($codes, $total);
            return [
                'carts' => $carts,
                'base' => NumberFormat::VND($base),
                'reduce' => NumberFormat::VND($base - $total),
                'discount' => NumberFormat::VND($discount),
                'total' => NumberFormat::VND($total - $discount),
                'paymentMethods' => PaymentMethod::getValues()
            ];
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    public function createOrder()
    {
        return DB::transaction(function () {
            $ids = Session::get('carts') ?? [];
            $codes = Session::get('codes') ?? [];
            $promotion = [];
            $discount = 0;

            list($courses, $base, $total) = $this->getInfoCourseByCart($ids);

            if (!empty($codes)) {
                $promotion = array_map([$this->couponRepository, 'findValidCode'], $codes);
                $discount = $this->makeDiscount($codes, $total);
            }

            $total -= $discount;
            $order = $this->orderRepository->create([
                'code' => Str::uuid(),
                'user_id' => $this->userId,
                'promotion' => json_encode($promotion),
                'discount' => $discount,
                'total' => $total,
            ]);

            $this->createOrderDetails($order->id, $courses);

            return [
                'orderId' => $order->id,
                'orderCode' => $order->code,
                'total' => $total,
            ];
        });
    }

    protected function createOrderDetails($orderId, array $courses)
    {
        foreach ($courses as &$course) {
            $course['order_id'] = $orderId;
            $this->orderDetailRepository->create($course);
        }
    }

    public function makeDiscount($codes, $total)
    {
        $discount = 0;
        try {
            if (!empty($codes)) {
                $discount = array_reduce($codes, function ($carry, $code) use ($total) {
                    return $carry + $this->makeDiscountCost($code, $total);
                }, 0);
                list($test, $limit) = $this->limitTest($total, $discount);
                if ($test) {
                    $discount = $limit;
                }
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
        return $discount;
    }

    public function getInfoCourseByCart($ids)
    {
        $base = 0; //base price
        $total = 0;
        $courses = [];
        try {
            foreach ($ids as $id) {
                $cart = $this->cartRepository->findById($id);
                if ($cart) {
                    $course = $this->courseRepository->find($cart->course_id);
                    if ($course['success']) {
                        $course = $course['course'];
                        $base += $course['fake_cost'];
                        $total += $course['cost'];
                        $courses[] = [
                            'course_id' => $course['id'],
                            'thumbnail' => $course['thumbnail'],
                            'course_name' => $course['name'],
                            'lecturer' => $course['lecturer'],
                            'cost' => $course['cost'],
                            'duration' => $course['duration'],
                        ];
                    }
                }
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
        return [$courses, $base, $total];
    }

    public function invoice($orderId)
    {
        try {
            $order = $this->orderRepository->getWithDetails($orderId);

            foreach ($order->details as $detail) {
                $detail->cost = NumberFormat::VND($detail->cost);
                $course = $this->courseRepository->find($detail->course_id);
                if ($course) {
                    $detail->lecturer = $course['course']['lecturer'];
                }
            }
            $date = Carbon::parse($order->created_at);
            return [
                'code' => $order->code,
                'base' => NumberFormat::VND($order->total + $order->discount),
                'discount' => NumberFormat::VND($order->discount),
                'total' => NumberFormat::VND($order->total),
                'courses' => $order->details,
                'created_at' =>  $date->format('H:i:s d-m-Y'),
            ];
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return [];
        }
    }
    public function invoicebyCode($code)
    {
        $orderId = $this->orderRepository->getIdByCode($code);
        if ($orderId) {
            return $this->invoice($orderId);
        }
    }
    public function sendInvoiceMail($orderId)
    {
        try {
            $order = $this->invoice($orderId);
            if ($order) {
                $order['customer'] = auth()->user()->name;
                $order['email'] = auth()->user()->email;
                SendInvoiceMail::dispatch($order)->onQueue('emails');
            }
        } catch (Exception $ex) {
            Log::error('[' . __METHOD__ . ']: ' . $ex->getMessage());
        }
    }
}
