<?php
/**
 * Herbama Fashions — CLI Administrator Creation Script
 * ====================================================
 * Usage:
 *   php scripts/create-admin.php
 *
 * Security:
 *   - Only runs via CLI (SAPI check).
 *   - Protected from HTTP web access via scripts/.htaccess and root .htaccess.
 *   - Enforces 12-character minimum password length.
 *   - Stores password using Argon2id / PASSWORD_DEFAULT.
 *   - Logs creation to audit_log table.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This script can only be executed from the command line.\n";
    exit(1);
}

// Ensure error display for CLI execution
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

echo "\n======================================================\n";
echo "    Herbama Fashions — CLI Admin Account Setup        \n";
echo "======================================================\n\n";

$userModel = new UserModel();
$hasAdmin  = $userModel->hasAdmin();

if ($hasAdmin) {
    echo "Notice: One or more administrator accounts already exist.\n";
    echo "Do you want to create an additional administrator? [y/N]: ";
    $confirm = trim((string)fgets(STDIN));
    if (strtolower($confirm) !== 'y') {
        echo "Aborted. No changes made.\n";
        exit(0);
    }
}

// ── Interactive Prompts Helper ────────────────────────────────────────────────
function cliPrompt(string $label, bool $required = true): string {
    while (true) {
        echo $label;
        $input = trim((string)fgets(STDIN));
        if (!$required || $input !== '') {
            return $input;
        }
        echo "  [Error] This field cannot be empty. Please enter a value.\n";
    }
}

// ── Prompt for Admin Details ──────────────────────────────────────────────────
$fullName = cliPrompt("1. Enter Administrator Full Name: ");

while (true) {
    $email = strtolower(cliPrompt("2. Enter Admin Email Address: "));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        break;
    }
    echo "  [Error] Invalid email address format. Please try again.\n";
}

while (true) {
    echo "3. Enter Password (minimum 12 characters): ";
    $password = trim((string)fgets(STDIN));
    if (strlen($password) < 12) {
        echo "  [Error] Password is too short (got " . strlen($password) . " characters). Minimum 12 required.\n";
        continue;
    }

    echo "4. Confirm Password: ";
    $confirm = trim((string)fgets(STDIN));
    if ($password !== $confirm) {
        echo "  [Error] Passwords do not match. Please re-enter.\n";
        continue;
    }
    break;
}

echo "\nCreating administrator account...\n";

if (!$hasAdmin) {
    $result = $userModel->setupInitialAdmin($fullName, $email, $password);
} else {
    // Creator is system/admin #1
    $result = $userModel->createAdmin($fullName, $email, $password, 1);
}

if ($result['ok']) {
    $adminId = $result['admin_id'] ?? $result['user_id'] ?? 1;
    echo "\n------------------------------------------------------\n";
    echo " [SUCCESS] Administrator account created successfully!\n";
    echo "------------------------------------------------------\n";
    echo "  Admin ID:  {$adminId}\n";
    echo "  Name:      {$fullName}\n";
    echo "  Email:     {$email}\n";
    echo "  Role:      admin\n";
    echo "  Sign In:   " . url('/login.php') . "\n";
    echo "======================================================\n\n";
    exit(0);
} else {
    echo "\n[ERROR] Failed to create administrator: " . ($result['error'] ?? 'Unknown error') . "\n\n";
    exit(1);
}
