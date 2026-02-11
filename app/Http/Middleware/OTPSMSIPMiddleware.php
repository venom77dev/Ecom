<?php

namespace App\Http\Middleware;

use App\Classes\StaticClasses;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OTPSMSIPMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        if (in_array($ip, StaticClasses::BLOCK_IPS)) {
            return response()->json(['message' => 'some thing is wrong...'], 403);
        }
        $oneHourAgo = Carbon::now()->subHour();

        $recordCount = DB::table('tbl_mobile_verification') // Replace with your table name
        ->where('receive_date', '>=', $oneHourAgo) // Check records within the last hour
        ->count();
        if ($recordCount > StaticClasses::OTP_LIMIT) {
            return response()->json(['message' => 'Rate limit exceeded. Users have made too many requests same Days.'], 429);
        }
        return $next($request);
    }
}
