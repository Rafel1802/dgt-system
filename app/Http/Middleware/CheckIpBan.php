<?php

namespace App\Http\Middleware;

use App\Models\IpBan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIpBan
{
    /**
     * Block requests from IPs that are in the ban list.
     * Checks the database for active, non-expired bans.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $deviceToken = $request->cookie('dgt_device_token');

        $banned = IpBan::active()
            ->where(function ($query) use ($ip, $deviceToken) {
                $query->where('ip_address', $ip);
                
                if ($deviceToken) {
                    $query->orWhere('device_token', $deviceToken);
                }
            })
            ->exists();

        if ($banned) {
            // Return a generic 403 to not reveal internal details
            abort(403, 'Access denied. Your IP address or device has been blocked.');
        }

        return $next($request);
    }
}
