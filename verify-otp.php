<?php
require_once 'config.php';

// Must be logged in already
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

$email = $_SESSION['user']['email'] ?? '';
$maskedEmail = $email;
if ($email) {
    $parts = explode('@', $email);
    if (strlen($parts[0]) > 2) {
        $maskedEmail = substr($parts[0], 0, 1)
                     . str_repeat('*', max(strlen($parts[0]) - 2, 1))
                     . substr($parts[0], -1)
                     . '@' . $parts[1];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['otp'] ?? '');
    $now  = time();

    if (empty($_SESSION['otp_code']) || empty($_SESSION['otp_expires_at']) || $now > $_SESSION['otp_expires_at']) {
        $error = 'Your code has expired. A new code has been sent to your email.';

        if ($email) {
            $otp = generateOtpCode();
            $_SESSION['otp_code']       = $otp;
            $_SESSION['otp_expires_at'] = $now + OTP_EXPIRY_SECONDS;
            sendOtpEmail($email, $otp);
        }
    } else {
        if (hash_equals($_SESSION['otp_code'], $code)) {
            $_SESSION['2fa_verified'] = true;
            unset($_SESSION['otp_code'], $_SESSION['otp_expires_at']);

            $redirect = $_SESSION['2fa_redirect'] ?? 'admin-home.php';
            unset($_SESSION['2fa_redirect']);

            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Invalid code. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP - ALLSHS eLMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center">
  <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-8">
    <div class="text-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800 mb-2">Two-Factor Verification</h1>
      <p class="text-gray-600 text-sm">
        We sent a 6-digit code to
        <span class="font-semibold">
          <?php echo htmlspecialchars($maskedEmail ?: 'your email'); ?>
        </span>.
      </p>
      <p class="text-gray-500 text-xs mt-1">
        Please do not share this code with anyone.
      </p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded">
        <p class="text-sm"><?php echo htmlspecialchars($error); ?></p>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          One-Time Password (OTP)
        </label>
        <input
          type="text"
          name="otp"
          maxlength="6"
          required
          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-center tracking-[0.4em] text-lg font-mono"
          placeholder="••••••"
        >
      </div>

      <button
        type="submit"
        class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition-colors"
      >
        Verify Code
      </button>

      <!-- 🔁 Resend OTP link -->
      <p class="mt-3 text-center text-sm text-gray-500">
        Didn’t receive the code?
        <a
          href="verify-otp-resend.php"
          class="text-indigo-600 font-semibold hover:underline"
        >
          Resend OTP
        </a>
      </p>
    </form>
  </div>
</body>
</html>
