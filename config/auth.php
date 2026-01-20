<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function set_acting_role(string $roleKey): void {
    
    $_SESSION['acting_role'] = $roleKey;
}

function get_acting_role(): string {
    return $_SESSION['acting_role'] ?? 'viewer';
}


function require_login(): void {
    if (!is_logged_in()) {
        header("Location: ?page=login");
        exit;
    }
}


function user_has_role(PDO $pdo, int $userId, string $roleKey): bool {
   
    if ($roleKey === 'viewer') return true;
    if ($roleKey === 'admin') {
        if (! $userId) return false;
       
        $st = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $st->execute([$userId]);
        $row = $st->fetch();
        if (! $row) return false;
        return strtolower($row['email']) === strtolower(ADMIN_EMAIL);
    }
   
    return false;
}

function is_super_admin(PDO $pdo, ?int $userId): bool {
    
    if (!$userId) return false;
    $st = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    $st->execute([$userId]);
    $row = $st->fetch();
    if (! $row) return false;
    return strtolower($row['email']) === strtolower(ADMIN_EMAIL);
}

function is_admin(PDO $pdo, ?int $userId): bool {
    return is_super_admin($pdo, $userId);
}



function is_volunteer(PDO $pdo, ?int $userId): bool {
  
    return false;
}


function ensure_acting_role(PDO $pdo, string $requiredRole): void {
    if ($requiredRole === 'viewer') {
       
        return;
    }

  
    require_login();

    $uid = current_user_id();
    if (is_super_admin($pdo, $uid)) {
        
        return;
    }

    $role = get_acting_role();
    if ($role !== $requiredRole) {
        // role selection UI removed; redirect to home with an error flag
        header("Location: ?page=home&err=role");
        exit;
    }
}
