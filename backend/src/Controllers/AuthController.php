<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;
use SepiaCoreUtilities\RateLimiter;

class AuthController extends BaseController
{
    /**
     * Extracts token from request (Bearer format, cookie, or query string).
     * Priority: Authorization header > Cookie > Query string.
     * @return string|null Token string or null if not found
     */
    public static function extractTokenFromRequest(): ?string
    {
        $request = Flight::request();
        
        // 1. Get token from Authorization header (Bearer format) - highest priority
        $authHeader = $request->getHeader('Authorization');
        if ($authHeader) {
            return preg_replace('/^Bearer\s+/i', '', $authHeader);
        }
        
        // 2. Get token from httpOnly cookie if enabled
        $useHttpOnlyCookies = $GLOBALS['config']['use_httponly_cookies'] ?? false;
        if ($useHttpOnlyCookies) {
            $cookieToken = $_COOKIE['auth_token'] ?? null;
            if ($cookieToken) {
                return $cookieToken;
            }
        }
        
        // 3. Fallback to query string if enabled in config (for legacy clients)
        $allowQueryToken = $GLOBALS['config']['allow_query_token'] ?? false;
        if ($allowQueryToken) {
            return $request->query['token'] ?? null;
        }
        
        return null;
    }

    /**
     * Verifies and validates a token.
     * @param string|null $token Token to verify
     * @return array{valid: bool, token: array|null, user: array|null}
     */
    public static function verifyToken($token): array
    {
        if (!$token) {
            return ['valid' => false, 'token' => null, 'user' => null];
        }

        $tokens = getEntityClass('Tokens');
        $storedToken = $tokens->find('token', $token);

        if (!$storedToken) {
            return ['valid' => false, 'token' => null, 'user' => null];
        }

        // Check token status
        if ($storedToken['status'] !== 'active') {
            return ['valid' => false, 'token' => $storedToken, 'user' => null];
        }

        // Check token expiration
        if (!empty($storedToken['expires_at'])) {
            $expiresAt = strtotime($storedToken['expires_at']);
            if ($expiresAt && time() > $expiresAt) {
                // Token expired - mark as inactive
                $GLOBALS['user_id'] = $storedToken['user_id'];
                $tokens->update($storedToken['id'], ['status' => 'inactive']);
                return ['valid' => false, 'token' => $storedToken, 'user' => null];
            }
        }

        // Load user
        $users = getEntityClass('Users');
        $user = $users->find('id', $storedToken['user_id']);

        if (!$user) {
            return ['valid' => false, 'token' => $storedToken, 'user' => null];
        }

        return [
            'valid' => true,
            'token' => $storedToken,
            'user' => $user
        ];
    }

    /**
     * Finds a valid (active, non-expired) token for a user.
     * @param string $userId User ID
     * @return array|null Token array or null if not found
     */
    private function findValidTokenForUser($userId): ?array
    {
        $tokens = $this->getEntityClass('Tokens');
        $allUserTokens = $tokens->find('user_id', $userId, false); // Get all tokens for user

        if (!$allUserTokens || empty($allUserTokens)) {
            return null;
        }

        foreach ($allUserTokens as $tokenRecord) {
            // Check if token is active
            if ($tokenRecord['status'] !== 'active') {
                continue;
            }
            
            // Check if token hasn't expired
            if (!empty($tokenRecord['expires_at'])) {
                $expiresAtTime = strtotime($tokenRecord['expires_at']);
                if ($expiresAtTime && time() > $expiresAtTime) {
                    // Token expired - mark as inactive
                    $GLOBALS['user_id'] = $userId;
                    $tokens->update($tokenRecord['id'], ['status' => 'inactive']);
                    continue;
                }
            }
            
            // Found a valid token
            return $tokenRecord;
        }

        return null;
    }

    /**
     * Gets client IP address (handles proxies).
     * @return string IP address
     */
    private function getClientIp(): string
    {
        $request = Flight::request();
        
        // Check various headers (in order of trust)
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            $ip = $request->getHeader($header) ?? $_SERVER[$header] ?? null;
            if ($ip) {
                // Handle comma-separated IPs (X-Forwarded-For)
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
                
                // Validate IP (prefer public IPs, but allow private for local dev)
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Handles user login with rate limiting.
     * @return void
     */
    public function login(): void
    {
        $request = Flight::request();
        $requestData = $request->data;

        $username = $requestData['username'] ?? null;
        $password = $requestData['password'] ?? null;
        
        // Get IP address (handle proxies)
        $ipAddress = $this->getClientIp();
        
        // Initialize rate limiter with config
        $maxAttempts = $GLOBALS['config']['rate_limit_max_attempts'] ?? 5;
        $timeWindow = $GLOBALS['config']['rate_limit_time_window'] ?? 900; // 15 minutes
        $blockDuration = $GLOBALS['config']['rate_limit_block_duration'] ?? 1800; // 30 minutes
        
        $rateLimiter = new RateLimiter($maxAttempts, $timeWindow, $blockDuration);
        
        // Check rate limit BEFORE processing login
        $rateLimitCheck = $rateLimiter->checkLimit($ipAddress, $username);
        
        if (!$rateLimitCheck['allowed']) {
            $retryAfter = $rateLimitCheck['retry_after'] ?? $blockDuration;
            $this->jsonResponse([
                'error' => 'Too many login attempts. Please try again later.',
                'retry_after' => $retryAfter
            ], 429); // 429 Too Many Requests
            return;
        }

        try {
            if (!$username || !$password) {
                $rateLimiter->recordFailure($ipAddress, $username);
                $this->jsonResponse(['error' => 'Username and password are required'], 400);
                return;
            }

            $users = $this->getEntityClass('Users');
            $user = $users->find('name', $username);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                // Record failed attempt
                $rateLimiter->recordFailure($ipAddress, $username);
                
                // Check again after recording (might have just hit the limit)
                $rateLimitCheck = $rateLimiter->checkLimit($ipAddress, $username);
                if (!$rateLimitCheck['allowed']) {
                    $retryAfter = $rateLimitCheck['retry_after'] ?? $blockDuration;
                    $this->jsonResponse([
                        'error' => 'Too many login attempts. Please try again later.',
                        'retry_after' => $retryAfter
                    ], 429);
                    return;
                }
                
                $this->jsonResponse([
                    'error' => 'Invalid credentials',
                    'remaining_attempts' => max(0, $rateLimitCheck['remaining'] - 1)
                ], 401);
                return;
            }

            // Successful login - clear rate limit records
            $rateLimiter->recordSuccess($ipAddress, $username);

            $isAdmin = (bool) $user['isadmin'];

            // Get token expiration from config (default 24 hours)
            $tokenExpiration = $GLOBALS['config']['token_expiration'] ?? 86400; // 24 hours in seconds
            $expiresAt = date('Y-m-d H:i:s', time() + $tokenExpiration);

            // Find existing VALID (active, non-expired) token
            $validToken = $this->findValidTokenForUser($user['id']);

            $tokenToReturn = null;
            
            if ($validToken) {
                // Update existing valid token with new expiration
                $GLOBALS['user_id'] = $user['id'];
                $tokens = $this->getEntityClass('Tokens');
                $tokens->update($validToken['id'], [
                    'expires_at' => $expiresAt
                ]);
                $tokenToReturn = $validToken['token'];
            } else {
                // No valid token found - generate new one
                $GLOBALS['user_id'] = $user['id'];
                $tokens = $this->getEntityClass('Tokens');
                $newToken = $tokens->create([
                    'user_id' => $user['id'],
                    'token' => bin2hex(random_bytes(32)),
                    'status' => 'active',
                    'expires_at' => $expiresAt
                ]);
                $tokenToReturn = $newToken['token'];
            }

            // Set httpOnly cookie if enabled
            $useHttpOnlyCookies = $GLOBALS['config']['use_httponly_cookies'] ?? false;
            if ($useHttpOnlyCookies) {
                $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                setcookie(
                    'auth_token',
                    $tokenToReturn,
                    [
                        'expires' => time() + $tokenExpiration,
                        'path' => '/',
                        'domain' => '',
                        'secure' => $isSecure, // Only send over HTTPS in production
                        'httponly' => true, // Prevent JavaScript access (XSS protection)
                        'samesite' => 'Lax' // CSRF protection
                    ]
                );
            }

            // Always return token in JSON response (for localStorage fallback in dev)
            $this->jsonResponse(['token' => $tokenToReturn, 'isAdmin' => $isAdmin]);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handles user logout by invalidating token.
     * @return void
     */
    public function logout(): void
    {
        $token = self::extractTokenFromRequest();

        if (!$token) {
            $this->jsonResponse(['error' => 'Token not provided'], 400);
            return;
        }

        try {
            $tokens = $this->getEntityClass('Tokens');
            $storedToken = $tokens->find('token', $token);

            if (!$storedToken) {
                // Token doesn't exist, but return success to avoid information leakage
                $this->jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
                return;
            }

            // Set token status to inactive instead of deleting (for audit trail)
            $GLOBALS['user_id'] = $storedToken['user_id'];
            $tokens->update($storedToken['id'], [
                'status' => 'inactive'
            ]);

            // Clear httpOnly cookie if enabled
            $useHttpOnlyCookies = $GLOBALS['config']['use_httponly_cookies'] ?? false;
            if ($useHttpOnlyCookies) {
                setcookie(
                    'auth_token',
                    '',
                    [
                        'expires' => time() - 3600, // Expire in the past
                        'path' => '/',
                        'domain' => '',
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]
                );
            }

            $this->jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}