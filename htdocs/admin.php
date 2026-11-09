<?php

declare(strict_types=1);

/**
 * Dedicated administrator interface.
 *
 * This is deliberately separate from the player SPA. It is server-rendered,
 * uses a private PHP session, checks the admin role in the database on every
 * request and requires a CSRF token for every mutation.
 */

use CasinoEngine\Admin\AdminSession;
use CasinoEngine\Core\Bootstrap;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Database\ConnectionInterface;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Registry\GameRegistryInterface;
use CasinoEngine\Persistence\Repository\ProbabilityRepository;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Persistence\Repository\WalletRepository;
use CasinoEngine\Services\Admin\AdminService;
use CasinoEngine\Services\Admin\GameControlService;
use CasinoEngine\Services\Auth\AuthServiceInterface;
use CasinoEngine\Services\Probability\ProbabilityConfigServiceInterface;
use CasinoEngine\Services\Probability\ProbabilityProfile;

$root = is_dir(__DIR__ . '/src') && is_file(__DIR__ . '/config/app.php')
    ? __DIR__
    : dirname(__DIR__);

$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Dependencies are not installed.';
    exit;
}

require $autoload;

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header(
    "Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
    . "style-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'self'"
);

$engine = Bootstrap::engine($root);
$engine->discoverGames();
$container = $engine->container();

$users = $container->get(UserRepository::class);
$wallets = $container->get(WalletRepository::class);
$registry = $container->get(GameRegistryInterface::class);
$controls = $container->get(GameControlService::class);
$probability = $container->get(ProbabilityConfigServiceInterface::class);
$auth = $container->get(AuthServiceInterface::class);
$connection = $container->get(ConnectionInterface::class);

$session = new AdminSession($auth, $users);
$session->start();

$adminService = new AdminService(
    $connection,
    $users,
    $wallets,
    $registry,
    $controls,
    $container->get(ClockInterface::class)
);

/** @param mixed $value */
function admin_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_credits(int $minor, int $scale = 2): string
{
    return number_format($minor / (10 ** max(0, $scale)), 2, '.', ',');
}

function admin_redirect(string $page = 'dashboard', ?int $userId = null): never
{
    $query = ['page' => $page];
    if ($userId !== null) {
        $query['user'] = $userId;
    }
    header('Location: admin.php?' . http_build_query($query));
    exit;
}

/** @param mixed $value */
function admin_flash(string $type, string $message): void
{
    $_SESSION['admin_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type:string,message:string}> */
function admin_take_flashes(): array
{
    $flashes = $_SESSION['admin_flash'] ?? [];
    unset($_SESSION['admin_flash']);

    return is_array($flashes) ? $flashes : [];
}

function admin_parse_credits(mixed $value): int
{
    $text = trim((string) $value);
    if (preg_match('/^-?\d+(?:\.\d{1,2})?$/', $text) !== 1) {
        throw new ValidationException('Enter credits as a number, for example 300 or -25.50.');
    }

    $negative = str_starts_with($text, '-');
    $text = ltrim($text, '-');
    [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
    $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

    return $negative ? -$minor : $minor;
}

$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : null;
$user = $session->user();

if ($action !== null) {
    if (!$session->verifyCsrf($_POST['csrf'] ?? null)) {
        http_response_code(419);
        admin_flash('error', 'Your form expired. Please try again.');
        $user = $session->user();
        $action = null;
    }
}

if ($action === 'login' && $user === null) {
    $lockout = $session->lockoutRemaining();
    if ($lockout > 0) {
        admin_flash('error', 'Too many attempts. Try again in ' . (int) ceil($lockout / 60) . ' minute(s).');
    } elseif ($session->attempt((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        admin_flash('success', 'Signed in.');
        admin_redirect();
    } else {
        admin_flash('error', 'Those administrator credentials were not accepted.');
    }
}

if ($action === 'logout' && $user !== null) {
    $session->logout();
    header('Location: admin.php');
    exit;
}

$user = $session->user();

if ($action !== null && $user !== null) {
    try {
        switch ($action) {
            case 'adjust':
                $userId = (int) ($_POST['user_id'] ?? 0);
                $newBalance = $adminService->adjustCredits(
                    $userId,
                    admin_parse_credits($_POST['amount'] ?? ''),
                    (string) ($_POST['reason'] ?? ''),
                    $user
                );
                admin_flash('success', 'Balance updated to ' . admin_credits($newBalance) . ' credits.');
                admin_redirect('user', $userId);
                // no break

            case 'status':
                $userId = (int) ($_POST['user_id'] ?? 0);
                $status = (string) ($_POST['status'] ?? '');
                $adminService->setStatus($userId, $status, $user);
                admin_flash('success', 'Account status changed to ' . $status . '.');
                admin_redirect('user', $userId);
                // no break

            case 'game-save':
                $gameId = (string) ($_POST['game_id'] ?? '');
                $game = $registry->get($gameId);

                $values = [];
                foreach (
                    [
                    'any_win_target', 'net_positive_target', 'rtp_target', 'jackpot_probability',
                    'bonus_trigger_probability', 'free_spin_trigger_probability', 'payout_scale',
                    ] as $field
                ) {
                    $raw = trim((string) ($_POST[$field] ?? ''));
                    $values[$field] = $raw === '' ? null : (float) $raw;
                }

                $friendly = isset($_POST['friendly_play']);
                if ($friendly) {
                    $preset = [
                        'any_win_target' => 0.45,
                        'net_positive_target' => 0.38,
                        'rtp_target' => 0.98,
                        'bonus_trigger_probability' => 0.18,
                        'free_spin_trigger_probability' => 0.16,
                        'payout_scale' => 1.15,
                    ];
                    foreach ($preset as $field => $presetValue) {
                        if ($values[$field] === null) {
                            $values[$field] = $presetValue;
                        }
                    }
                }

                $values['enabled'] = true;
                foreach ($values as $field => $value) {
                    if ($value === null) {
                        unset($values[$field]);
                    }
                }

                try {
                    ProbabilityProfile::fromArray($values, $gameId);
                } catch (\CasinoEngine\Core\Exception\ConfigurationException $error) {
                    throw new ValidationException($error->getMessage());
                }

                $probability->saveOverride($gameId, $values, (int) $user['id'], (string) $user['username']);
                $controls->save($gameId, isset($_POST['available']), $friendly, (int) $user['id']);

                admin_flash(
                    'success',
                    $game->metadata()->name() . ' saved. ' . ($friendly ? 'Friendly play is active.' : 'Standard play.')
                );
                admin_redirect('games');
                // no break

            case 'game-reset':
                $gameId = (string) ($_POST['game_id'] ?? '');
                $game = $registry->get($gameId);
                $probability->resetToGlobal($gameId, (int) $user['id'], (string) $user['username']);
                $controls->save($gameId, true, false, (int) $user['id']);
                admin_flash('success', $game->metadata()->name() . ' reset to global defaults and made available.');
                admin_redirect('games');
                // no break

            case 'global-save':
                $values = [];
                foreach (
                    [
                    'any_win_target', 'net_positive_target', 'rtp_target', 'jackpot_probability',
                    'bonus_trigger_probability', 'free_spin_trigger_probability', 'payout_scale',
                    ] as $field
                ) {
                    $raw = trim((string) ($_POST[$field] ?? ''));
                    $values[$field] = $raw === '' ? null : (float) $raw;
                }
                $values['enabled'] = true;
                foreach ($values as $field => $value) {
                    if ($value === null) {
                        unset($values[$field]);
                    }
                }

                try {
                    ProbabilityProfile::fromArray($values);
                } catch (\CasinoEngine\Core\Exception\ConfigurationException $error) {
                    throw new ValidationException($error->getMessage());
                }

                $probability->saveGlobal($values, (int) $user['id'], (string) $user['username']);
                admin_flash('success', 'Global game defaults saved.');
                admin_redirect('games');
                // no break
        }
    } catch (ValidationException $error) {
        admin_flash('error', $error->getMessage());
        admin_redirect($action === 'global-save' ? 'games' : ($action === 'game-save' || $action === 'game-reset' ? 'games' : 'users'));
    } catch (\Throwable $error) {
        $engine->logger()->error('admin action failed', [
            'action' => $action,
            'message' => $error->getMessage(),
            'exception' => $error::class,
        ]);
        admin_flash('error', 'The action could not be completed.');
        admin_redirect('dashboard');
    }
}

$flashes = admin_take_flashes();

if ($user === null) {
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Administrator sign in</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-login-body">
<main class="admin-login">
    <div class="admin-login__brand">
        <span class="admin-mark">A</span>
        <div>
            <h1>Aurum Control</h1>
            <p>Administrator access</p>
        </div>
    </div>

    <?php foreach ($flashes as $flash) : ?>
        <div class="admin-alert admin-alert--<?= admin_e($flash['type']) ?>"><?= admin_e($flash['message']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="admin.php" class="admin-form">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= admin_e($session->csrfToken()) ?>">
        <label>
            <span>Username</span>
            <input type="text" name="username" autocomplete="username" required autofocus>
        </label>
        <label>
            <span>Password</span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="admin-button admin-button--primary" type="submit">Sign in</button>
    </form>
    <p class="admin-login__note">This area is for operators only. Player accounts cannot sign in here.</p>
</main>
</body>
</html>
    <?php
    exit;
}

$page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'dashboard';
if (!in_array($page, ['dashboard', 'users', 'user', 'games', 'audit'], true)) {
    $page = 'dashboard';
}

$dashboard = null;
$playerRows = [];
$detail = null;
$auditRows = [];
$gameRows = [];
$global = $probability->globalProfile();

if ($page === 'dashboard') {
    $dashboard = $adminService->dashboard();
} elseif ($page === 'users') {
    $search = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
    $playerRows = $adminService->users($search);
} elseif ($page === 'user') {
    $detail = $adminService->user((int) ($_GET['user'] ?? 0));
    if ($detail === null) {
        admin_flash('error', 'That account was not found.');
        admin_redirect('users');
    }
} elseif ($page === 'audit') {
    $auditRows = $adminService->auditLog();
} else {
    foreach ($registry->all(false) as $metadata) {
        $gameId = $metadata->id();
        $override = $probability->overrides()[$gameId] ?? null;
        $gameRows[] = [
            'metadata'   => $metadata,
            'effective'  => $probability->effectiveFor($gameId),
            'override'   => $override,
            'available'  => $controls->isAvailable($gameId),
            'friendly'   => $controls->isFriendlyPlay($gameId),
        ];
    }
}

$csrf = $session->csrfToken();
$currentPage = $page;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Aurum Control</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="admin.php">
            <span class="admin-mark">A</span>
            <span><strong>Aurum</strong><small>Control room</small></span>
        </a>
        <nav class="admin-nav" aria-label="Administrator">
            <a class="<?= $currentPage === 'dashboard' ? 'is-active' : '' ?>" href="admin.php?page=dashboard">
                <span class="admin-nav__icon">◈</span> Dashboard
            </a>
            <a class="<?= $currentPage === 'users' || $currentPage === 'user' ? 'is-active' : '' ?>" href="admin.php?page=users">
                <span class="admin-nav__icon">◉</span> Players
            </a>
            <a class="<?= $currentPage === 'games' ? 'is-active' : '' ?>" href="admin.php?page=games">
                <span class="admin-nav__icon">◇</span> Games
            </a>
            <a class="<?= $currentPage === 'audit' ? 'is-active' : '' ?>" href="admin.php?page=audit">
                <span class="admin-nav__icon">▤</span> Audit log
            </a>
        </nav>
        <div class="admin-sidebar__foot">
            <a href="index.php" target="_blank" rel="noopener">Open game lobby ↗</a>
            <form method="post" action="admin.php">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf" value="<?= admin_e($csrf) ?>">
                <button type="submit" class="admin-link-button">Sign out</button>
            </form>
            <small><?= admin_e($user['username']) ?></small>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div>
                <p class="admin-eyebrow">Operator console</p>
                <h1>
                    <?= $currentPage === 'dashboard' ? 'Dashboard' : '' ?>
                    <?= $currentPage === 'users' || $currentPage === 'user' ? 'Players' : '' ?>
                    <?= $currentPage === 'games' ? 'Game configuration' : '' ?>
                    <?= $currentPage === 'audit' ? 'Audit log' : '' ?>
                </h1>
            </div>
            <div class="admin-topbar__status"><span></span> Secure session</div>
        </header>

        <?php foreach ($flashes as $flash) : ?>
            <div class="admin-alert admin-alert--<?= admin_e($flash['type']) ?>"><?= admin_e($flash['message']) ?></div>
        <?php endforeach; ?>

        <?php if ($currentPage === 'dashboard' && $dashboard !== null) : ?>
            <section class="admin-metrics">
                <article class="admin-metric">
                    <span>Registered players</span>
                    <strong><?= number_format($dashboard['total_users']) ?></strong>
                    <small><?= number_format($dashboard['active_users']) ?> active · <?= number_format($dashboard['suspended_users']) ?> suspended</small>
                </article>
                <article class="admin-metric">
                    <span>Recently active</span>
                    <strong><?= number_format($dashboard['online_users']) ?></strong>
                    <small>In a game in the last 15 minutes</small>
                </article>
                <article class="admin-metric">
                    <span>Credits in circulation</span>
                    <strong><?= admin_credits($dashboard['credits_minor']) ?></strong>
                    <small>Across <?= number_format($dashboard['wallets']) ?> wallets</small>
                </article>
                <article class="admin-metric">
                    <span>Games registered</span>
                    <strong><?= number_format($dashboard['games']) ?></strong>
                    <small><?= number_format($dashboard['friendly_games']) ?> marked friendly play</small>
                </article>
            </section>

            <section class="admin-grid admin-grid--two">
                <article class="admin-card">
                    <div class="admin-card__head">
                        <h2>Game activity</h2>
                        <span>All time · <?= number_format($dashboard['rounds']) ?> rounds</span>
                    </div>
                    <div class="admin-statline">
                        <div><span>Wagered</span><strong><?= admin_credits($dashboard['wagered_minor']) ?></strong></div>
                        <div><span>Returned</span><strong><?= admin_credits($dashboard['returned_minor']) ?></strong></div>
                        <div><span>Net</span><strong class="<?= $dashboard['net_minor'] >= 0 ? 'is-positive' : 'is-negative' ?>"><?= admin_credits($dashboard['net_minor']) ?></strong></div>
                    </div>
                    <?php if ($dashboard['game_activity'] === []) : ?>
                        <p class="admin-empty">No rounds have been played yet.</p>
                    <?php else : ?>
                        <table class="admin-table admin-table--compact">
                            <thead><tr><th>Game</th><th>Rounds (7d)</th><th>Wagered</th><th>Returned</th></tr></thead>
                            <tbody>
                            <?php foreach ($dashboard['game_activity'] as $row) : ?>
                                <tr>
                                    <td><code><?= admin_e($row['game_id']) ?></code></td>
                                    <td><?= number_format((int) $row['rounds']) ?></td>
                                    <td><?= admin_credits((int) $row['wagered']) ?></td>
                                    <td><?= admin_credits((int) $row['returned']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </article>

                <article class="admin-card">
                    <div class="admin-card__head">
                        <h2>Recent registrations</h2>
                        <a href="admin.php?page=users">View all</a>
                    </div>
                    <?php if ($dashboard['recent_users'] === []) : ?>
                        <p class="admin-empty">No player accounts yet.</p>
                    <?php else : ?>
                        <ul class="admin-list">
                            <?php foreach ($dashboard['recent_users'] as $row) : ?>
                                <li>
                                    <a href="admin.php?page=user&amp;user=<?= (int) $row['id'] ?>">
                                        <span class="admin-avatar"><?= admin_e(mb_substr((string) $row['display_name'], 0, 1)) ?></span>
                                        <span><strong><?= admin_e($row['display_name']) ?></strong><small><?= admin_e($row['phone'] ?? 'No phone') ?></small></span>
                                        <span class="admin-list__end"><?= admin_credits((int) ($row['balance_minor'] ?? 0)) ?><small><?= admin_e($row['created_at']) ?></small></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            </section>

            <section class="admin-card">
                <div class="admin-card__head">
                    <h2>Recent game activity</h2>
                    <span>Live ledger</span>
                </div>
                <?php if ($dashboard['recent_rounds'] === []) : ?>
                    <p class="admin-empty">No rounds yet. Activity will appear here as players play.</p>
                <?php else : ?>
                    <table class="admin-table">
                        <thead><tr><th>Player</th><th>Game</th><th>Bet</th><th>Payout</th><th>Net</th><th>When</th></tr></thead>
                        <tbody>
                        <?php foreach ($dashboard['recent_rounds'] as $row) : ?>
                            <tr>
                                <td><?= admin_e($row['display_name'] ?? 'Deleted player') ?></td>
                                <td><code><?= admin_e($row['game_id']) ?></code></td>
                                <td><?= admin_credits((int) $row['bet_minor']) ?></td>
                                <td><?= admin_credits((int) $row['payout_minor']) ?></td>
                                <td class="<?= (int) $row['net_minor'] >= 0 ? 'is-positive' : 'is-negative' ?>"><?= admin_credits((int) $row['net_minor']) ?></td>
                                <td class="admin-muted"><?= admin_e($row['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($currentPage === 'users') : ?>
            <section class="admin-card">
                <div class="admin-card__head">
                    <h2>Player accounts</h2>
                    <form class="admin-search" method="get" action="admin.php">
                        <input type="hidden" name="page" value="users">
                        <input type="search" name="q" value="<?= admin_e($_GET['q'] ?? '') ?>" placeholder="Search phone, name or ID">
                        <button type="submit">Search</button>
                    </form>
                </div>
                <?php if ($playerRows === []) : ?>
                    <p class="admin-empty">No accounts matched.</p>
                <?php else : ?>
                    <table class="admin-table">
                        <thead><tr><th>Player</th><th>Phone</th><th>Balance</th><th>Rounds</th><th>Status</th><th>Joined</th></tr></thead>
                        <tbody>
                        <?php foreach ($playerRows as $row) : ?>
                            <tr>
                                <td><a href="admin.php?page=user&amp;user=<?= (int) $row['id'] ?>"><strong><?= admin_e($row['display_name']) ?></strong><small class="admin-muted"> #<?= (int) $row['id'] ?></small></a></td>
                                <td><?= admin_e($row['phone'] ?? '—') ?></td>
                                <td><?= admin_credits((int) ($row['balance_minor'] ?? 0), (int) ($row['scale'] ?? 2)) ?></td>
                                <td><?= number_format((int) $row['rounds']) ?></td>
                                <td><span class="admin-badge admin-badge--<?= admin_e($row['status']) ?>"><?= admin_e($row['status']) ?></span></td>
                                <td class="admin-muted"><?= admin_e($row['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($currentPage === 'user' && $detail !== null) : ?>
            <div class="admin-grid admin-grid--two">
                <article class="admin-card">
                    <div class="admin-card__head">
                        <h2><?= admin_e($detail['display_name']) ?></h2>
                        <span class="admin-badge admin-badge--<?= admin_e($detail['status']) ?>"><?= admin_e($detail['status']) ?></span>
                    </div>
                    <dl class="admin-details">
                        <div><dt>Phone</dt><dd><?= admin_e($detail['phone'] ?? '—') ?></dd></div>
                        <div><dt>Age</dt><dd><?= admin_e($detail['age'] ?? '—') ?></dd></div>
                        <div><dt>Role</dt><dd><?= admin_e($detail['role']) ?></dd></div>
                        <div><dt>Balance</dt><dd class="admin-balance"><?= admin_credits((int) ($detail['balance_minor'] ?? 0), (int) ($detail['scale'] ?? 2)) ?> <small>credits</small></dd></div>
                        <div><dt>Joined</dt><dd><?= admin_e($detail['created_at']) ?></dd></div>
                        <div><dt>Last updated</dt><dd><?= admin_e($detail['updated_at']) ?></dd></div>
                    </dl>

                    <?php if ((string) $detail['role'] !== 'admin') : ?>
                        <div class="admin-actions">
                            <form method="post" action="admin.php" class="admin-inline-form">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="csrf" value="<?= admin_e($csrf) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $detail['id'] ?>">
                                <input type="hidden" name="status" value="<?= $detail['status'] === 'active' ? 'suspended' : 'active' ?>">
                                <button class="admin-button <?= $detail['status'] === 'active' ? 'admin-button--danger' : 'admin-button--primary' ?>" type="submit">
                                    <?= $detail['status'] === 'active' ? 'Suspend account' : 'Reactivate account' ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="admin-card">
                    <div class="admin-card__head"><h2>Adjust credits</h2><span>Audited</span></div>
                    <form method="post" action="admin.php" class="admin-form">
                        <input type="hidden" name="action" value="adjust">
                        <input type="hidden" name="csrf" value="<?= admin_e($csrf) ?>">
                        <input type="hidden" name="user_id" value="<?= (int) $detail['id'] ?>">
                        <label>
                            <span>Amount</span>
                            <input type="text" name="amount" inputmode="decimal" placeholder="e.g. 300 or -50" required>
                            <small>Positive adds credits, negative removes them. The balance cannot go below zero.</small>
                        </label>
                        <label>
                            <span>Reason</span>
                            <input type="text" name="reason" maxlength="200" placeholder="Friendly top-up" required>
                        </label>
                        <button class="admin-button admin-button--primary" type="submit">Apply adjustment</button>
                    </form>
                </article>
            </div>

            <section class="admin-card">
                <div class="admin-card__head"><h2>Recent wallet activity</h2><span>Newest first</span></div>
                <?php if ($detail['transactions'] === []) : ?>
                    <p class="admin-empty">No wallet movements yet.</p>
                <?php else : ?>
                    <table class="admin-table">
                        <thead><tr><th>Type</th><th>Reason</th><th>Amount</th><th>Balance after</th><th>Reference</th><th>When</th></tr></thead>
                        <tbody>
                        <?php foreach ($detail['transactions'] as $row) : ?>
                            <tr>
                                <td><span class="admin-badge admin-badge--<?= admin_e($row['type']) ?>"><?= admin_e($row['type']) ?></span></td>
                                <td><?= admin_e($row['reason']) ?></td>
                                <td class="<?= $row['type'] === 'credit' ? 'is-positive' : 'is-negative' ?>"><?= admin_credits((int) $row['amount_minor']) ?></td>
                                <td><?= admin_credits((int) $row['balance_after_minor']) ?></td>
                                <td><code class="admin-code"><?= admin_e($row['reference']) ?></code></td>
                                <td class="admin-muted"><?= admin_e($row['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($currentPage === 'games') : ?>
            <section class="admin-card">
                <div class="admin-card__head">
                    <h2>Global defaults</h2>
                    <span>Applied to games without an override</span>
                </div>
                <p class="admin-help">These targets steer the engine; they are not guaranteed per-round outcomes. Leave a field blank to leave that metric unconstrained. A friendly-play game gets a more generous preset when its own fields are blank.</p>
                <form method="post" action="admin.php" class="admin-form admin-form--grid">
                    <input type="hidden" name="action" value="global-save">
                    <input type="hidden" name="csrf" value="<?= admin_e($csrf) ?>">
                    <?php foreach (
                    [
                        'any_win_target' => 'Any-win target',
                        'net_positive_target' => 'Net-positive target',
                        'rtp_target' => 'RTP target',
                        'jackpot_probability' => 'Jackpot probability',
                        'bonus_trigger_probability' => 'Bonus trigger probability',
                        'free_spin_trigger_probability' => 'Free-spin trigger probability',
                        'payout_scale' => 'Payout scale',
                    ] as $field => $label
) : ?>
                        <label>
                            <span><?= admin_e($label) ?></span>
                            <input type="text" inputmode="decimal" name="<?= admin_e($field) ?>" value="<?= $global->toArray()[$field] === null ? '' : admin_e((string) $global->toArray()[$field]) ?>" placeholder="Unconstrained">
                        </label>
                    <?php endforeach; ?>
                    <div class="admin-form__actions">
                        <button class="admin-button admin-button--primary" type="submit">Save global defaults</button>
                    </div>
                </form>
            </section>

            <?php foreach ($gameRows as $row) : ?>
                <?php
                $metadata = $row['metadata'];
                $effective = $row['effective']->toArray();
                $override = $row['override']?->toArray();
                $values = $override ?? $effective;
                ?>
                <details class="admin-card admin-game" <?= $row['friendly'] ? 'open' : '' ?>>
                    <summary>
                        <div>
                            <strong><?= admin_e($metadata->name()) ?></strong>
                            <small><code><?= admin_e($metadata->id()) ?></code> · <?= admin_e($metadata->category()->value) ?> · published RTP <?= admin_e(number_format((float) $metadata->rtp() * 100, 2)) ?>%</small>
                        </div>
                        <div class="admin-game__flags">
                            <?php if (!$row['available']) :
                                ?><span class="admin-badge admin-badge--suspended">Unavailable</span><?php
                            endif; ?>
                            <?php if ($row['friendly']) :
                                ?><span class="admin-badge admin-badge--friendly">Friendly play</span><?php
                            endif; ?>
                            <?php if ($row['override'] !== null) :
                                ?><span class="admin-badge admin-badge--override">Override</span><?php
                            else :
                                ?><span class="admin-badge">Global</span><?php
                            endif; ?>
                        </div>
                    </summary>

                    <form method="post" action="admin.php" class="admin-form admin-form--grid admin-game__form">
                        <input type="hidden" name="action" value="game-save">
                        <input type="hidden" name="csrf" value="<?= admin_e($csrf) ?>">
                        <input type="hidden" name="game_id" value="<?= admin_e($metadata->id()) ?>">

                        <?php foreach (
                        [
                            'any_win_target' => 'Any-win target',
                            'net_positive_target' => 'Net-positive target',
                            'rtp_target' => 'RTP target',
                            'jackpot_probability' => 'Jackpot probability',
                            'bonus_trigger_probability' => 'Bonus trigger probability',
                            'free_spin_trigger_probability' => 'Free-spin trigger probability',
                            'payout_scale' => 'Payout scale',
                        ] as $field => $label
) : ?>
                            <label>
                                <span><?= admin_e($label) ?></span>
                                <input type="text" inputmode="decimal" name="<?= admin_e($field) ?>" value="<?= isset($values[$field]) && $values[$field] !== null ? admin_e((string) $values[$field]) : '' ?>" placeholder="Unconstrained">
                            </label>
                        <?php endforeach; ?>

                        <label class="admin-check">
                            <input type="checkbox" name="available" <?= $row['available'] ? 'checked' : '' ?>>
                            <span>Available in the lobby</span>
                        </label>
                        <label class="admin-check">
                            <input type="checkbox" name="friendly_play" <?= $row['friendly'] ? 'checked' : '' ?>>
                            <span>Friendly play <small>Uses generosity presets for blank fields and marks the game clearly.</small></span>
                        </label>

                        <div class="admin-form__actions">
                            <button class="admin-button admin-button--primary" type="submit">Save game settings</button>
                        </div>
                    </form>

                    <?php if ($row['override'] !== null) : ?>
                        <form method="post" action="admin.php" class="admin-form__reset">
                            <input type="hidden" name="action" value="game-reset">
                            <input type="hidden" name="csrf" value="<?= admin_e($csrf) ?>">
                            <input type="hidden" name="game_id" value="<?= admin_e($metadata->id()) ?>">
                            <button class="admin-link-button" type="submit">Reset to global defaults</button>
                        </form>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($currentPage === 'audit') : ?>
            <section class="admin-card">
                <div class="admin-card__head"><h2>Audit trail</h2><span>Most recent 100 events</span></div>
                <?php if ($auditRows === []) : ?>
                    <p class="admin-empty">No audit events yet.</p>
                <?php else : ?>
                    <table class="admin-table">
                        <thead><tr><th>Event</th><th>Player</th><th>Context</th><th>IP</th><th>When</th></tr></thead>
                        <tbody>
                        <?php foreach ($auditRows as $row) : ?>
                            <tr>
                                <td><code><?= admin_e($row['event']) ?></code></td>
                                <td><?= admin_e($row['display_name'] ?? ($row['user_id'] ?? '—')) ?></td>
                                <td><code class="admin-code"><?= admin_e((string) ($row['context'] ?? '')) ?></code></td>
                                <td class="admin-muted"><?= admin_e($row['ip_address'] ?? '—') ?></td>
                                <td class="admin-muted"><?= admin_e($row['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
<script src="assets/js/admin.js"></script>
</body>
</html>
