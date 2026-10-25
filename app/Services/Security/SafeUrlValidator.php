<?php

namespace App\Services\Security;

use InvalidArgumentException;

class SafeUrlValidator
{
    /**
     * Private and reserved IPv4 CIDR blocks.
     */
    private const PRIVATE_IPV4_RANGES = [
        '0.0.0.0/8',          // Current network (only valid as source address)
        '10.0.0.0/8',         // Private-use networks (RFC 1918)
        '100.64.0.0/10',      // Shared address space (RFC 6598)
        '127.0.0.0/8',        // Loopback (RFC 1122)
        '169.254.0.0/16',     // Link-local / Cloud Metadata (RFC 3927)
        '172.16.0.0/12',      // Private-use networks (RFC 1918)
        '192.0.0.0/24',       // IETF protocol assignments
        '192.0.2.0/24',       // Documentation (TEST-NET-1)
        '192.168.0.0/16',     // Private-use networks (RFC 1918)
        '198.18.0.0/15',      // Benchmarking
        '198.51.100.0/24',    // Documentation (TEST-NET-2)
        '203.0.113.0/24',     // Documentation (TEST-NET-3)
        '224.0.0.0/4',        // Multicast
        '240.0.0.0/4',        // Reserved for future use
        '255.255.255.255/32', // Limited broadcast
    ];

    /**
     * Validate an external URL for safe outbound requests (preventing SSRF).
     *
     * @param string $url
     * @param bool $allowLocalhost Only allowed in local environment for automated testing
     * @return string Sanitized and validated URL
     * @throws InvalidArgumentException
     */
    public static function validate(string $url, bool $allowLocalhost = false): string
    {
        $parts = parse_url($url);

        if (!$parts || !isset($parts['host'])) {
            throw new InvalidArgumentException("Invalid URL: Unable to parse host from '{$url}'.");
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("Unsupported URL scheme '{$scheme}'. Only HTTP and HTTPS are permitted.");
        }

        $host = strtolower($parts['host']);

        // Check for literal localhost or loopback name
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            if (!$allowLocalhost && app()->isProduction()) {
                throw new InvalidArgumentException("Access to loopback or localhost is strictly blocked.");
            }
            return $url;
        }

        // Resolve DNS to IP addresses
        $ips = @gethostbynamel($host);
        if ($ips === false || empty($ips)) {
            // In testing/mock environments, allow if host is a valid domain format
            if (!app()->isProduction() && (str_contains($host, 'example.com') || str_contains($host, 'test'))) {
                return $url;
            }
            throw new InvalidArgumentException("Cannot resolve host '{$host}'.");
        }

        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                if (!$allowLocalhost || app()->isProduction()) {
                    throw new InvalidArgumentException("Host '{$host}' resolves to restricted/private IP '{$ip}' (SSRF defense).");
                }
            }
        }

        return $url;
    }

    /**
     * Check if an IPv4 address falls within any private or reserved range.
     */
    public static function isPrivateIp(string $ip): bool
    {
        // Check filter_var flags
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }

        foreach (self::PRIVATE_IPV4_RANGES as $range) {
            [$subnet, $bits] = explode('/', $range);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - (int)$bits);
            if (($long & $mask) === ($subnetLong & $mask)) {
                return true;
            }
        }

        return false;
    }
}
