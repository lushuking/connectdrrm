<?php
/**
 * Test Environment Setup
 * This script creates multiple test users for performance testing.
 */
require_once __DIR__ . '/../config/db.php';

$testUsers = [
    [
        'email' => 'tester@connectdrrm.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'admin',
        'fullName' => 'Test Admin'
    ],
    [
        'email' => 'staff@connectdrrm.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'drrmo_staff',
        'fullName' => 'Test Staff'
    ]
];

try {
    foreach ($testUsers as $user) {
        $testEmail = $user['email'];
        $testPass = $user['password'];
        $role = $user['role'];
        $fullName = $user['fullName'];

        // Check if user exists
        $stmt = $pdo->prepare("SELECT userID FROM users WHERE email = ?");
        $stmt->execute([$testEmail]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing test user
            $stmt = $pdo->prepare("UPDATE users SET password = ?, role = ?, profileCompleted = 1 WHERE userID = ?");
            $stmt->execute([$testPass, $role, $existing['userID']]);
            echo "Test user $testEmail updated.\n";
        } else {
            // Create new test user
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role, profileCompleted, fullName, position, contactNumber, signature) VALUES (?, ?, ?, 1, ?, 'Performance Tester', '09123456789', 'test-signature')");
            $stmt->execute([$testEmail, $testPass, $role, $fullName]);
            echo "Test user $testEmail created.\n";
        }
    }
} catch (Exception $e) {
    echo "Error setting up test users: " . $e->getMessage() . "\n";
}
?>
