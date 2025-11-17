<?php

namespace SepiaCoreUtilities;

use Illuminate\Database\Capsule\Manager as Capsule;
use Ramsey\Uuid\Uuid;

class RateLimiter
{
    private $maxAttempts;
    private $timeWindow; // in seconds
    private $blockDuration; // in seconds

    public function __construct($maxAttempts = 5, $timeWindow = 900, $blockDuration = 1800)
    {
        $this->maxAttempts = $maxAttempts;
        $this->timeWindow = $timeWindow; // 15 minutes default
        $this->blockDuration = $blockDuration; // 30 minutes default
    }

    /**
     * Check if IP/username is rate limited
     * Returns ['allowed' => bool, 'remaining' => int, 'retry_after' => int|null, 'reason' => string|null]
     */
    public function checkLimit($ipAddress, $username = null): array
    {
        $now = date('Y-m-d H:i:s');
        $cutoffTime = date('Y-m-d H:i:s', time() - $this->timeWindow);

        // Check if currently blocked
        $blocked = Capsule::table('login_attempts')
            ->where('ip_address', $ipAddress)
            ->where(function($query) use ($username) {
                if ($username) {
                    $query->where('username', $username)
                          ->orWhereNull('username');
                } else {
                    $query->whereNull('username');
                }
            })
            ->whereNotNull('blocked_until')
            ->where('blocked_until', '>', $now)
            ->first();

        if ($blocked) {
            $retryAfter = strtotime($blocked->blocked_until) - time();
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => max(0, $retryAfter),
                'reason' => 'blocked'
            ];
        }

        // Count attempts in time window
        $attempts = Capsule::table('login_attempts')
            ->where('ip_address', $ipAddress)
            ->where(function($query) use ($username) {
                if ($username) {
                    $query->where('username', $username)
                          ->orWhereNull('username');
                } else {
                    $query->whereNull('username');
                }
            })
            ->where('last_attempt', '>=', $cutoffTime)
            ->sum('attempts');

        $attempts = (int) $attempts;
        $remaining = max(0, $this->maxAttempts - $attempts);

        if ($attempts >= $this->maxAttempts) {
            // Block this IP/username
            $this->block($ipAddress, $username);
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => $this->blockDuration,
                'reason' => 'rate_limit_exceeded'
            ];
        }

        return [
            'allowed' => true,
            'remaining' => $remaining,
            'retry_after' => null,
            'reason' => null
        ];
    }

    /**
     * Record a failed login attempt
     */
    public function recordFailure($ipAddress, $username = null): void
    {
        $now = date('Y-m-d H:i:s');
        $cutoffTime = date('Y-m-d H:i:s', time() - $this->timeWindow);

        // Find or create attempt record
        $attempt = Capsule::table('login_attempts')
            ->where('ip_address', $ipAddress)
            ->where(function($query) use ($username) {
                if ($username) {
                    $query->where('username', $username)
                          ->orWhereNull('username');
                } else {
                    $query->whereNull('username');
                }
            })
            ->where('last_attempt', '>=', $cutoffTime)
            ->first();

        if ($attempt) {
            // Update existing record
            Capsule::table('login_attempts')
                ->where('id', $attempt->id)
                ->update([
                    'attempts' => Capsule::raw('attempts + 1'),
                    'last_attempt' => $now,
                    'username' => $username
                ]);
        } else {
            // Create new record
            Capsule::table('login_attempts')->insert([
                'id' => Uuid::uuid4()->toString(),
                'ip_address' => $ipAddress,
                'username' => $username,
                'attempts' => 1,
                'first_attempt' => $now,
                'last_attempt' => $now
            ]);
        }
    }

    /**
     * Record successful login (clear attempts)
     */
    public function recordSuccess($ipAddress, $username = null): void
    {
        Capsule::table('login_attempts')
            ->where('ip_address', $ipAddress)
            ->where(function($query) use ($username) {
                if ($username) {
                    $query->where('username', $username)
                          ->orWhereNull('username');
                } else {
                    $query->whereNull('username');
                }
            })
            ->delete();
    }

    /**
     * Block IP/username
     */
    private function block($ipAddress, $username = null): void
    {
        $blockedUntil = date('Y-m-d H:i:s', time() + $this->blockDuration);
        
        Capsule::table('login_attempts')
            ->where('ip_address', $ipAddress)
            ->where(function($query) use ($username) {
                if ($username) {
                    $query->where('username', $username)
                          ->orWhereNull('username');
                } else {
                    $query->whereNull('username');
                }
            })
            ->update(['blocked_until' => $blockedUntil]);
    }

    /**
     * Clean up old records (call via cron or on login)
     */
    public function cleanup($olderThanDays = 7): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($olderThanDays * 86400));
        Capsule::table('login_attempts')
            ->where('last_attempt', '<', $cutoff)
            ->whereNull('blocked_until')
            ->delete();
    }
}

