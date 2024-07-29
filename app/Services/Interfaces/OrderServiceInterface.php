<?php

namespace App\Services\Interfaces;

interface OrderServiceInterface
{
    public function show();
    // public function find($id);
    public function createOrder();
    public function invoice(int $orderId);
    public function invoicebyCode($code);
    public function sendInvoiceMail($orderId);
    // public function getUsedCodesByUser();
}
