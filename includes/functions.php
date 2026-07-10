<?php
declare(strict_types=1);

function sanitizeInput(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function displayFlashMessage(): string {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        // Use standard alert classes based on type
        $cssClass = $flash['type'] === 'error' ? 'alert-error' : 'alert-success';
        return "<div class='alert {$cssClass}'>" . htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') . "</div>";
    }
    return "";
}
