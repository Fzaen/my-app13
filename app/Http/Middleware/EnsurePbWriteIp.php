<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePbWriteIp
{
    /**
     * @var list<string>
     */
    private const ALLOWED_IP_ADDRESSES = [
        '192.168.198.30',
        '192.168.198.35',
        '192.168.198.40',
        '192.168.198.41',
        '192.168.198.42',
        '192.168.198.43',
        '192.168.198.44',
        '192.168.198.45',
        '127.0.0.1',
        '::1',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $this->normalizeIpAddress($request->ip());

        abort_unless(
            in_array($clientIp, self::ALLOWED_IP_ADDRESSES, true),
            403,
            "Alamat IP {$clientIp} tidak diizinkan untuk menyimpan data PB.",
        );

        return $next($request);
    }

    private function normalizeIpAddress(?string $ipAddress): ?string
    {
        if ($ipAddress === null) {
            return null;
        }

        $ipv4MappedPrefix = '::ffff:';
        $normalizedIpAddress = str_starts_with(strtolower($ipAddress), $ipv4MappedPrefix)
            ? substr($ipAddress, strlen($ipv4MappedPrefix))
            : $ipAddress;

        return filter_var($normalizedIpAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            ? $normalizedIpAddress
            : $ipAddress;
    }
}
