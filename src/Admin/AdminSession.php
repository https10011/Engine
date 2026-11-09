<?php

declare(strict_types=1);

namespace CasinoEngine\Admin;

use CasinoEngine\Core\Exception\AuthenticationException;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Services\Auth\AuthServiceInterface;

/**
 * Session-backed administrator authentication.
 *
 * Player requests use bearer tokens; the administrator page uses a separate
 * PHP session with its own cookie name. Authorisation is checked against the
 * database on every request, so hiding a link in the browser is never the
 * control. CSRF tokens are required for every state-changing form.
 */
final class AdminSession
{
    private const SESSION_NAME = 'casino_admin';
    private const IDLE_SECONDS = 1800;
    private const MAX_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW = 600;

    public function __construct(
        private readonly AuthServiceInterface $auth,
        private readonly UserRepository $users
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $this->start();

        $admin = $_SESSION['admin'] ?? null;
        if (!is_array($admin) || !isset($admin['id'])) {
            return null;
        }

        $lastSeen = (int) ($_SESSION['admin_last_seen'] ?? 0);
        if ($lastSeen > 0 && time() - $lastSeen > self::IDLE_SECONDS) {
            $this->logout();

            return null;
        }

        $_SESSION['admin_last_seen'] = time();

        // Re-read the role on every request. Demoting or suspending the account
        // takes effect immediately, even if the session cookie is still valid.
        $fresh = $this->users->findById((int) $admin['id']);
        if ($fresh === null || ($fresh['role'] ?? 'player') !== 'admin' || ($fresh['status'] ?? '') !== 'active') {
            $this->logout();

            return null;
        }

        return $fresh;
    }

    public function attempt(string $username, string $password): bool
    {
        $this->start();

        $now = time();
        $attempts = $_SESSION['admin_attempts'] ?? ['count' => 0, 'first' => $now];
        if (!is_array($attempts)) {
            $attempts = ['count' => 0, 'first' => $now];
        }
        if ($now - (int) ($attempts['first'] ?? $now) > self::ATTEMPT_WINDOW) {
            $attempts = ['count' => 0, 'first' => $now];
        }
        if ((int) ($attempts['count'] ?? 0) >= self::MAX_ATTEMPTS) {
            return false;
        }

        try {
            $user = $this->auth->verifyPassword($username, $password, $_SERVER['REMOTE_ADDR'] ?? null);
        } catch (AuthenticationException) {
            $attempts['count'] = (int) ($attempts['count'] ?? 0) + 1;
            $_SESSION['admin_attempts'] = $attempts;

            return false;
        }

        if (($user['role'] ?? 'player') !== 'admin' || ($user['status'] ?? '') !== 'active') {
            $attempts['count'] = (int) ($attempts['count'] ?? 0) + 1;
            $_SESSION['admin_attempts'] = $attempts;

            return false;
        }

        // A new session id after login prevents session fixation.
        session_regenerate_id(true);
        $_SESSION = [
            'admin'            => $user,
            'admin_last_seen'  => $now,
            'admin_csrf'       => bin2hex(random_bytes(32)),
        ];

        return true;
    }

    public function logout(): void
    {
        $this->start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }

        session_destroy();
    }

    public function csrfToken(): string
    {
        $this->start();
        if (!isset($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf'])) {
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['admin_csrf'];
    }

    public function verifyCsrf(mixed $token): bool
    {
        $this->start();
        $expected = $_SESSION['admin_csrf'] ?? null;

        return is_string($token) && is_string($expected) && hash_equals($expected, $token);
    }

    /** Seconds until the next login attempt is allowed again. */
    public function lockoutRemaining(): int
    {
        $this->start();
        $attempts = $_SESSION['admin_attempts'] ?? null;
        if (!is_array($attempts) || (int) ($attempts['count'] ?? 0) < self::MAX_ATTEMPTS) {
            return 0;
        }

        return max(0, self::ATTEMPT_WINDOW - (time() - (int) ($attempts['first'] ?? time())));
    }
}
