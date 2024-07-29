<?php

namespace App\Services;

use Exception;
use PayOS\PayOS;
use App\Helpers\HttpStatus;
use App\Enums\PaymentMethod;
use App\Helpers\APIResponse;
use App\Helpers\NumberFormat;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Services\Interfaces\BankServiceInterface;
use App\Services\Interfaces\TransactionServiceInterface;

class BankService implements BankServiceInterface
{

    private $payosReturnUrl;
    private $payosClientId;
    private $payosApiKey;
    private $payosChecksumKey;
    private $vietqrUrl;
    private $vietqrClientId;
    private $vietqrApiKey;
    private $vietqrTemplate;
    private $payOS;


    public function __construct(
        protected TransactionServiceInterface $transactionService
    ) {
        $this->payosReturnUrl = env('PAYOS_RETURN_URL');
        $this->payosClientId = env('PAYOS_CLIENT_ID');
        $this->payosApiKey = env('PAYOS_API_KEY');
        $this->payosChecksumKey = env('PAYOS_CHECKSUM_KEY');
        $this->vietqrUrl = env('VIETQR_URL');
        $this->vietqrClientId = env('VIETQR_CLIENT_ID');
        $this->vietqrApiKey = env('VIETQR_API_KEY');
        $this->vietqrTemplate = env('VIETQR_TEMPLATE');
        $this->initPayOS();
    }
    private function initPayOS()
    {
        if (!$this->payOS) {
            $this->payOS = new PayOS(
                $this->payosClientId,
                $this->payosApiKey,
                $this->payosChecksumKey
            );
        }
    }

    public function create($request)
    {
        try {
            $timeout = 300;
            $data = [
                "orderCode" => $request->orderId,
                "amount" =>  $request->total,
                "description" => 'Thanh toan hoa don',
                "returnUrl" => $this->payosReturnUrl,
                "cancelUrl" => $this->payosReturnUrl,
                "expiredAt" => time() + $timeout
            ];
            $response = $this->payOS->createPaymentLink($data);
            $qrURL = QrCode::size(300)
                ->errorCorrection('M')
                ->generate($response['qrCode']);
            Session::put('orderId', $response['orderCode']);
            return APIResponse::make(
                HttpStatus::OK,
                '',
                [
                    'qrURL' => 'data:image/svg+xml;base64,' . base64_encode($qrURL),
                    'accountName' => $response['accountName'],
                    'amount' => NumberFormat::VND($response['amount']),
                    'timeout' => $timeout
                ]
            );
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return APIResponse::make(HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    public function checkBank()
    {
        try {
            $orderId = Session::get('orderId');
            if ($orderId) {
                $response = $this->payOS->getPaymentLinkInformation($orderId);
                if ($response['status'] === 'PAID') {
                    Session::forget('orderId');
                    $trans = $this->transactionService->create($response, PaymentMethod::BANK);
                    if ($trans) {
                        Session::put('result', $trans);
                        return APIResponse::make(
                            HttpStatus::OK,
                            'Payment successfully.',
                            ['url' => route('orders.result')]
                        );
                    } else {
                        return APIResponse::make(HttpStatus::INTERNAL_SERVER_ERROR, 'Failed to create transaction.');
                    }
                } else {
                    return APIResponse::make(HttpStatus::NO_CONTENT, 'Payment not completed yet.');
                }
            } else {
                return APIResponse::make(HttpStatus::BAD_REQUEST, 'Order ID not found in session.');
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return APIResponse::make(HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    public function createQR($accountNo, $accountName, $bin, $description, $amount)
    {
        try {
            $response = Http::withHeaders([
                'x-client-id' => $this->vietqrClientId,
                'x-api-key' => $this->vietqrApiKey,
                'Content-Type' => 'application/json'
            ])->post(
                $this->vietqrUrl,
                [
                    'accountNo' => $accountNo,
                    'accountName' => $accountName,
                    'acqId' => $bin,
                    'addInfo' => $description,
                    'amount' => $amount,
                    'template' => $this->vietqrTemplate
                ]
            );
            if ($response->successful()) {
                $responseData = $response->json();
                if ($responseData['code'] == '00') {
                    return $responseData['data']['qrDataURL'];
                }
            }
            return response()->json([
                'error' => 'Error occurred',
                'status' => $response->status(),
                'message' => $response->body()
            ], $response->status());
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return APIResponse::make(HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }
}
