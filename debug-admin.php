<?php
session_start();
require_once 'config.php';

echo "<h2>Admin Login Debug</h2>";

$db = getDB();

// Check if admin exists
$stmt = $db->prepare("SELECT * FROM admins WHERE username = 'admin'");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Admin Account Check:</h3>";
if ($admin) {
    echo "✅ Admin account found!<br>";
    echo "Username: " . $admin['username'] . "<br>";
    echo "Password Hash: " . $admin['password_hash'] . "<br>";
    echo "Role: " . $admin['role'] . "<br>";
    
    // Test password verification
    $testPassword = 'admin123';
    $isValid = password_verify($testPassword, $admin['password_hash']);
    echo "Password 'admin123' verification: " . ($isValid ? '✅ VALID' : '❌ INVALID') . "<br>";
    
    if (!$isValid) {
        echo "Let's create a new admin account with the correct password...<br>";
        
        // Delete old admin
        $stmt = $db->prepare("DELETE FROM admins WHERE username = 'admin'");
        $stmt->execute();
        
        // Create new admin with correct password
        $newHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO admins (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@allshs.edu', $newHash, 'superadmin']);
        
        echo "✅ New admin account created with password 'admin123'<br>";
    }
    
} else {
    echo "❌ No admin account found! Creating one now...<br>";
    
    // Create admin account
    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO admins (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@allshs.edu', $adminHash, 'superadmin']);
    
    echo "✅ Admin account created!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
}

echo "<hr>";
echo "<h3>Try logging in now:</h3>";
echo "<form action='login.php' method='post'>";
echo "<input type='hidden' name='username' value='admin'>";
echo "<input type='hidden' name='password' value='admin123'>";
echo "<input type='hidden' name='role' value='admin'>";
echo "<button type='submit'>Login as Admin</button>";
echo "</form>";

echo "<hr>";
echo "<h3>All Admin Accounts:</h3>";
$stmt = $db->prepare("SELECT * FROM admins");
$stmt->execute();
$allAdmins = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($allAdmins as $admin) {
    echo "Username: " . $admin['username'] . " | Role: " . $admin['role'] . "<br>";
}
?>