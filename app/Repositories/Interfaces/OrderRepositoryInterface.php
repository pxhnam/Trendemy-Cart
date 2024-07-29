<?php

namespace App\Repositories\Interfaces;

interface OrderRepositoryInterface
{
    public function find($id);
    public function getWithDetails($id);
    public function getIdByCode($code);
    public function create($data);
    public function getUsedPromotionsForUser();
}
