<?php

namespace App\Http\Middleware;

use Closure;
use App\Helpers\APIResponse;
use App\Helpers\HttpStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class authenticate
{

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }
        return APIResponse::make(HttpStatus::UNAUTHORIZED);
    }
}
