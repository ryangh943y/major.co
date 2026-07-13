<?php
// forgot_password.php
session_start();
require_once 'backend/db.php';

$step = 1;
$error = '';
$success = '';
$email = '';
$question = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'submit_email') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Kindly enter a valid email address.';
        } else {
            $stmt = $pdo->prepare("SELECT id, security_question FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'We could not find an account with that email.';
            } else if (empty($user['security_question'])) {
                $error = 'No security question set for this account. Kindly contact support.';
            } else {
                $_SESSION['reset_email'] = $email;
                $question = $user['security_question'];
                $step = 2;
            }
        }
    } else if ($action === 'submit_answer') {
        $email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
        $answer = isset($_POST['security_answer']) ? trim($_POST['security_answer']) : '';

        if (!$email) {
            $error = 'Session expired. Kindly try again.';
            $step = 1;
        } else if (!$answer) {
            $error = 'Kindly enter the answer to your security question.';
            $step = 2;
            // Retrieve question again
            $stmt = $pdo->prepare("SELECT security_question FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $question = $stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT security_answer_hash, security_question FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            $question = $user['security_question'];

            if ($user && password_verify(strtolower($answer), $user['security_answer_hash'])) {
                $_SESSION['reset_allowed'] = true;
                $step = 3;
            } else {
                $error = 'Incorrect answer to security question. Kindly check and try again.';
                $step = 2;
            }
        }
    } else if ($action === 'submit_password') {
        $email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
        $allowed = isset($_SESSION['reset_allowed']) ? $_SESSION['reset_allowed'] : false;
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if (!$email || !$allowed) {
            $error = 'Unauthorized access. Kindly try again from start.';
            $step = 1;
        } else if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
            $step = 3;
        } else if ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
            $step = 3;
        } else {
            try {
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, security_token = NULL WHERE email = ?");
                $stmt->execute([$new_hash, $email]);

                // Cleanup session variables
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_allowed']);

                $success = 'Password reset successfully. You will be redirected to the login page shortly.';
                $step = 4;
            } catch (Exception $e) {
                $error = 'Failed to reset password: ' . $e->getMessage();
                $step = 3;
            }
        }
    }
} else {
    // GET request: cleanup variables
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_allowed']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Recover your password using your security question response.">
    <title>Recover Password | ProjectCrew</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #F0F4FF 0%, #EEF2FF 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before, body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
        }
        body::before {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(108,92,231,0.15), transparent 70%);
            top: -120px; right: -100px;
        }
        body::after {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(74,144,226,0.15), transparent 70%);
            bottom: -100px; left: -80px;
        }

        .recovery-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(226,232,240,0.8);
            border-radius: 24px;
            padding: 44px 40px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 24px 64px rgba(108,92,231,0.12), 0 8px 24px rgba(0,0,0,0.06);
            animation: scaleIn 0.45s cubic-bezier(0.34,1.56,0.64,1) both;
            position: relative;
            z-index: 1;
        }

        .rec-logo {
            display: flex; align-items: center; gap: 10px;
            justify-content: center; margin-bottom: 24px; text-decoration: none;
        }
        .rec-logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #6C5CE7, #4A90E2);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 14px rgba(108,92,231,0.4);
        }
        .rec-logo-icon svg { color: white; width: 22px; height: 22px; }
        .rec-logo-text { font-size: 20px; font-weight: 700; color: #1A1D2E; }

        .rec-title    { font-size: 22px; font-weight: 700; color: #1A1D2E; text-align: center; margin-bottom: 6px; }
        .rec-subtitle { font-size: 13px; color: #6B7280; text-align: center; margin-bottom: 28px; }

        /* Field styling */
        .field-label {
            display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 7px;
        }
        .field-input {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            font-size: 14px; color: #1A1D2E;
            background: #FAFBFF; outline: none;
            transition: all 0.2s;
        }
        .field-input:focus {
            border-color: #6C5CE7; background: white;
            box-shadow: 0 0 0 3px rgba(108,92,231,0.12);
        }

        .submit-btn {
            width: 100%; padding: 13px; font-size: 14px; font-weight: 600;
            color: white;
            background: linear-gradient(135deg, #6C5CE7 0%, #4A90E2 100%);
            border: none; border-radius: 10px; cursor: pointer;
            transition: all 0.25s;
            box-shadow: 0 4px 14px rgba(108,92,231,0.4);
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(108,92,231,0.45);
        }

        /* Alert banner */
        .alert-banner {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 500;
            margin-bottom: 20px; animation: fadeInUp 0.3s ease;
        }
        .alert-banner.error   { background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; }
        .alert-banner.success { background: #E0FBF5; color: #059669; border: 1px solid #A7F3D0; }

        @media (max-width: 480px) {
            .recovery-card { padding: 32px 20px; }
        }
    </style>
</head>
<body>
    <div class="recovery-card">
        <!-- Logo -->
        <a href="index.html" class="rec-logo">
            <div class="rec-logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <span class="rec-logo-text">ProjectCrew</span>
        </a>

        <h1 class="rec-title">Recover Password 🔑</h1>
        <p class="rec-subtitle">Restore access to your partner network workspace</p>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert-banner error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-banner success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- STEP 1: Enter Email -->
            <form method="POST" action="">
                <input type="hidden" name="action" value="submit_email">
                <div style="margin-bottom:20px;">
                    <label for="email" class="field-label">Email Address</label>
                    <input id="email" name="email" type="email" required class="field-input" placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <button type="submit" class="submit-btn">Next Step</button>
            </form>
        <?php endif; ?>

        <?php if ($step === 2): ?>
            <!-- STEP 2: Answer Security Question -->
            <form method="POST" action="">
                <input type="hidden" name="action" value="submit_answer">
                <div class="p-4 bg-blue-50/50 border border-blue-100 rounded-xl mb-4 text-xs font-semibold text-blue-800 leading-relaxed">
                    Question: <?php echo htmlspecialchars($question); ?>
                </div>
                <div style="margin-bottom:20px;">
                    <label for="security_answer" class="field-label">Answer</label>
                    <input id="security_answer" name="security_answer" type="text" required class="field-input" placeholder="Type your answer here" autocomplete="off" autofocus>
                </div>
                <button type="submit" class="submit-btn">Verify Answer</button>
            </form>
        <?php endif; ?>

        <?php if ($step === 3): ?>
            <!-- STEP 3: Enter New Password -->
            <form method="POST" action="" id="resetForm" onsubmit="return validatePasswords()">
                <input type="hidden" name="action" value="submit_password">
                <div style="margin-bottom:16px;">
                    <label for="password" class="field-label">New Password</label>
                    <input id="password" name="password" type="password" required class="field-input" placeholder="Minimum 8 characters">
                </div>
                <div style="margin-bottom:20px;">
                    <label for="confirm_password" class="field-label">Confirm New Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" required class="field-input" placeholder="Repeat new password">
                    <div id="pass-match-error" style="display:none;" class="text-xs text-red-500 mt-1">Passwords do not match.</div>
                </div>
                <button type="submit" class="submit-btn">Reset Password</button>
            </form>
            <script>
                function validatePasswords() {
                    const pass = document.getElementById('password').value;
                    const confirm = document.getElementById('confirm_password').value;
                    const err = document.getElementById('pass-match-error');
                    if (pass !== confirm) {
                        err.style.display = 'block';
                        return false;
                    }
                    err.style.display = 'none';
                    return true;
                }
            </script>
        <?php endif; ?>

        <?php if ($step === 4): ?>
            <!-- STEP 4: Success Redirection -->
            <script>
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 3000);
            </script>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 22px; font-size: 13px; color: #6B7280;">
            <a href="login.html" style="color: #6C5CE7; font-weight: 600; text-decoration: none;">Back to Login</a>
        </div>
    </div>
</body>
</html>
