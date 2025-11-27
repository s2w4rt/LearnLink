<?php
require_once 'config.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$email = $_SESSION['user']['email'] ?? null;

if ($email) {
    $otp = generateOtpCode();
    $_SESSION['otp_code']       = $otp;
    $_SESSION['otp_expires_at'] = time() + OTP_EXPIRY_SECONDS;
    sendOtpEmail($email, $otp);
}

header('Location: verify-otp.php');
exit;
