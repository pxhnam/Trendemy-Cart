<?php

namespace App\Services;

use Exception;
use App\Enums\CartState;
use App\Helpers\HttpStatus;
use App\Helpers\APIResponse;
use App\Helpers\NumberFormat;
use App\Traits\DiscountTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Services\Interfaces\CartServiceInterface;
use App\Services\Interfaces\ConfigServiceInterface;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use App\Repositories\Interfaces\CourseRepositoryInterface;

class CartService implements CartServiceInterface
{
    use DiscountTrait;


    public function __construct(
        protected CartRepositoryInterface $cartRepository,
        protected CourseRepositoryInterface $courseRepository,
        protected CouponRepositoryInterface $couponRepository,
        protected OrderRepositoryInterface $orderRepository,
        protected ConfigServiceInterface $configService,
    ) {
    }

    public function list()
    {
        try {
            $carts = $this->cartRepository->listCart();
            $courses = [];
            if ($carts->count()) {
                foreach ($carts as $cart) {
                    $course = $this->courseRepository->find($cart->course_id);
                    if ($course['success']) {
                        $course = $course['course'];
                        $course['id'] = $cart->id; #use cart_id
                        $course['fake_cost'] = NumberFormat::VND($course['fake_cost']);
                        $course['cost'] = NumberFormat::VND($course['cost']);
                        $courses[] = $course;
                    } else {
                        $this->cartRepository->removeFromCart($cart->id);
                    }
                }
                if (count($courses)) {
                    return APIResponse::make(HttpStatus::OK, '', $courses);
                }
            }
            return APIResponse::make(HttpStatus::NO_CONTENT);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return APIResponse::make(HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    public function add($courseId)
    {
        try {
            if ($this->courseRepository->check($courseId)) {
                $cart = $this->cartRepository->findByCourseId($courseId);
                if ($cart) {
                    switch ($cart->state) {
                        case CartState::PENDING:
                            return APIResponse::make(HttpStatus::OK, 'Course is already in the cart.');
                        case CartState::REMOVED:
                            $cart->state = CartState::PENDING;
                            $cart->save();
                            return APIResponse::make(HttpStatus::OK, 'Course has been added to the cart.');
                        case CartState::PURCHASED:
                            return APIResponse::make(HttpStatus::OK, 'You have already purchased this course.');
                        default:
                            return APIResponse::make(HttpStatus::INTERNAL_SERVER_ERROR, 'An unexpected error occurred.');
                    }
                } else {
                    $newCart = $this->cartRepository->addToCart($courseId);
                    if ($newCart) {
                        return APIResponse::make(HttpStatus::CREATED, 'Course has been added to the cart.');
                    } else {
                        return APIResponse::make(HttpStatus::INTERNAL_SERVER_ERROR, 'Please try again later.');
                    }
                }
            } else {
                return APIResponse::make(HttpStatus::NOT_FOUND, 'Course does not exist.');
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return APIResponse::make(HttpStatus::INTERNAL_SERVER_ERROR, 'Server error occurred.');
        }
    }

    public function summary($data)
    {
        try {
            $ids = $data->ids;
            $codes = $data->codes;
            $ids = array_unique($ids ?? []);
            $codes = array_unique($codes ?? []);
            $basePrice = 0;
            $totalPrice = 0;
            $discount = 0;
            $coupons = [];
            $count = 0;
            if (!empty($ids)) {
                list($basePrice, $totalPrice) = $this->makeTotalCarts($ids);

                $coupons = [
                    'data' => $this->findValidCouponsByCost($totalPrice) ?? [],
                    'limit' => false
                ];
            }

            if (!empty($codes)) {
                $codes = array_combine($codes, $codes);
                foreach ($codes as $code => $value) {
                    $reduce = $this->makeDiscountCost($code, $totalPrice);
                    $codes[$code] = NumberFormat::VND($reduce);
                    if ($reduce) {
                        $discount += $reduce;
                        list($test, $limit) = $this->limitTest($totalPrice, $discount);
                        if ($test) {
                            $count++;
                            if ($limit != $discount) {
                                $codes[$code] = NumberFormat::VND($limit - ($discount - $reduce));
                            }
                            $discount = $limit;
                            $coupons['limit'] = true;
                            if ($count > 1) {
                                unset($codes[$code]);
                            }
                        }
                    } else {
                        unset($codes[$code]);
                    }
                }
            }
            return APIResponse::make(
                HttpStatus::OK,
                '',
                [
                    'basePrice' => NumberFormat::VND($basePrice),
                    'reducePrice' => NumberFormat::VND($basePrice - $totalPrice),
                    'subTotal' => NumberFormat::VND($totalPrice),
                    'totalPrice' => NumberFormat::VND($totalPrice - $discount),
                    'codes' => $codes,
                    'coupons' => $coupons
                ]
            );
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return APIResponse::make(HttpStatus::INTERNAL_SERVER_ERROR, 'Server error occurred.');
        }
    }

    public function checkout($data)
    {
        try {
            Session::forget(['carts', 'codes']);
            $ids = $data->ids;
            $codes = $data->codes;
            $ids = array_unique($ids ?? []);
            $codes = array_unique($codes ?? []);
            $carts = collect($ids)->filter(function ($id) {
                return $this->cartRepository->existsById($id);
            })->values()->all();
            if (!empty($carts)) {
                Session::put('carts', $carts);
                if (!empty($codes)) {
                    list($base, $total) = $this->makeTotalCarts($carts);
                    $codes = array_filter($codes, function ($code) use ($total) {
                        return $this->makeDiscountCost($code, $total) !== 0;
                    });
                    Session::put('codes', $codes);
                }
                return APIResponse::make(HttpStatus::OK, '', ['url' => route('orders.checkout')]);
            } else {
                return APIResponse::make(HttpStatus::NO_CONTENT, 'No courses selected.');
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return APIResponse::make(HttpStatus::INTERNAL_SERVER_ERROR, 'Server error occurred.');
        }
    }

    public function remove($id)
    {
        try {
            // Gate::authorize('delete', $cart);
            $removeCart = $this->cartRepository->removeFromCart($id);
            if ($removeCart) {
                $count = $this->count();
                return APIResponse::make(HttpStatus::OK, 'Course removed from cart successfully.', ['count' => $count]);
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return APIResponse::make(HttpStatus::INTERNAL_SERVER_ERROR, 'Server error occurred.');
        }
    }

    public function makeTotalCarts($ids)
    {
        $basePrice = 0;
        $totalPrice = 0;
        try {
            if (!empty($ids)) {
                foreach ($ids as $id) {
                    $cart = $this->cartRepository->findById($id);
                    $course = $this->courseRepository->find($cart->course_id);
                    if ($course['success']) {
                        $basePrice += $course['course']['fake_cost'];
                        $totalPrice += $course['course']['cost'];
                    }
                }
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
        return [$basePrice, $totalPrice];
    }

    public function listRecommend()
    {
        $coursesId = $this->cartRepository->getCoursesIdNotInCart();
        $courses = $this->courseRepository->getRandomCoursesNotInCart($coursesId);
        $courses = array_map(function ($course) {
            $course['cost'] = NumberFormat::VND($course['cost']);
            $course['fake_cost'] = NumberFormat::VND($course['fake_cost']);
            return $course;
        }, $courses ?? []);
        return $courses;
    }
    public function count()
    {
        return $this->cartRepository->countCart();
    }
}
