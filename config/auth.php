<?php
// config/auth.php
// Session + auth/role helpers for Deadlock-Rise-of-the-Zombies

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** ---------- Session / identity ---------- */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function set_acting_role(string $roleKey): void {
    // 'viewer' or a roles.role_key (e.g., 'admin')
    $_SESSION['acting_role'] = $roleKey;
}

function get_acting_role(): string {
    return $_SESSION['acting_role'] ?? 'viewer';
}

/**
 * Require an authenticated session for non-public pages.
 * Uses a relative redirect so the project folder name doesn't matter.
 */
function require_login(): void {
    if (!is_logged_in()) {
        header("Location: ?page=login");
        exit;
    }
}

/** ---------- Role checks (RBAC) ---------- */
function user_has_role(PDO $pdo, int $userId, string $roleKey): bool {
    if ($roleKey === 'viewer') {
        // Viewer is always allowed
        return true;
    }
    $sql = "SELECT COUNT(*) 
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.role_key = ?";
    $st = $pdo->prepare($sql);
    $st->execute([$userId, $roleKey]);
    return (int)$st->fetchColumn() > 0;
}

function is_super_admin(PDO $pdo, ?int $userId): bool {
    if (!$userId) return false;
    $sql = "SELECT COUNT(*)
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.role_key = 'admin'";
    $st = $pdo->prepare($sql);
    $st->execute([$userId]);
    return (int)$st->fetchColumn() > 0;
}

/**
 * Page-level guard for role-protected pages.
 * - 'viewer' is PUBLIC: do not call this for the viewer page.
 * - For any other role:
 *      * must be logged in,
 *      * super_admin bypasses and can see all role pages,
 *      * otherwise acting_role must match requiredRole.
 */
function ensure_acting_role(PDO $pdo, string $requiredRole): void {
    if ($requiredRole === 'viewer') {
        // Public page; no checks.
        return;
    }

    // Must be logged in for any non-viewer page
    require_login();

    $uid = current_user_id();
    if (is_super_admin($pdo, $uid)) {
        // Super Admin can access everything
        return;
    }

    $role = get_acting_role();
    if ($role !== $requiredRole) {
        // Send user to role chooser with an error
        header("Location: ?page=role_select&err=role");
        exit;
    }
}
