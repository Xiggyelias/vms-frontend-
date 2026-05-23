<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Generate CSRF token for forms
$csrfToken = SecurityMiddleware::generateCSRFToken();

// Prepare alert messages (rendered later inside the page)
$alert_type = null;
$alert_message = null;

if (isset($_GET['error'])) {
    $error = $_GET['error'];
    if ($error === 'empty_fields') {
        $alert_type = 'danger';
        $alert_message = 'Please fill in all fields.';
    } elseif ($error === 'invalid_password') {
        $alert_type = 'danger';
        $alert_message = 'Invalid password.';
    } elseif ($error === 'not_found') {
        $alert_type = 'warning';
        $alert_message = 'Account not found.';
    } elseif ($error === 'google_failed') {
        $alert_type = 'danger';
        $alert_message = 'Google sign-in failed. Please try again.';
    } elseif ($error === 'domain_not_allowed') {
        $alert_type = 'danger';
        $alert_message = 'Only Africa University email addresses are allowed.';
    } elseif ($error === 'invalid_role') {
        $alert_type = 'warning';
        $alert_message = 'Google login is only available for students and staff.';
    } elseif ($error === 'suspended') {
        $alert_type = 'danger';
        $alert_message = 'Your account has been suspended. Please contact the administrator.';
    }
}

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $alert_type = 'success';
    $alert_message = 'Your password has been reset successfully. Please login with your new password.';
}

// Handle login result
if (isset($login_successful) && $login_successful) {
    // Save logged-in user ID to session
    $_SESSION['user_id'] = $user['applicant_id'];
    header("Location: user-dashboard.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>Login - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <div class="login-page">
        <div class="login-left">
            <div class="login-form">
                <div class="login-header">
                    <div class="logo">
                        <img src="assets/images/AULogo.png" alt="AU Logo">
                    </div>
                    <h1>Welcome Back</h1>
                    <p>Please log in to your account</p>
                </div>

                <?php if ($alert_type && $alert_message): ?>
                    <div class="alert alert-<?= htmlspecialchars($alert_type) ?>">
                        <?= htmlspecialchars($alert_message) ?>
                    </div>
                <?php endif; ?>


                <div id="googleLoginForm">
                    <div style="display:flex; flex-direction:column; gap:1rem; align-items:center;">
                        <!-- Google Sign-In Button -->
                        <div id="g_id_onload"
                             data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>"
                             data-context="signin"
                             data-ux_mode="popup"
                             data-callback="handleGoogleCredential"
                             data-auto_select="false"
                             data-itp_support="true"
                             data-use_fedcm_for_prompt="false">
                        </div>
                        <div class="g_id_signin"
                             data-type="icon"
                             data-shape="circle"
                             data-theme="filled_blue"
                             data-text=""
                             data-size="large"
                             data-logo_alignment="center">
                        </div>
                        
                    </div>
                </div>

            </div>
        </div>

        <div class="login-right">
            <div class="welcome-text">
                <h2>Vehicle Registration System</h2>
                <p>Manage your vehicle registrations efficiently and securely. Keep track of all your vehicles in one place.</p>
            </div>
        </div>
    </div>

    <script>
        // Same-origin proxy path for API calls (avoids cross-origin CORS issues).
        const BACKEND_BASE_PATH = '/backend';
        // Direct backend URL for OAuth redirect fallback — the callback is registered
        // on the backend domain so it cannot go through the same-origin proxy.
        const BACKEND_DIRECT_URL = '<?= rtrim(htmlspecialchars(BACKEND_URL, ENT_QUOTES, 'UTF-8'), '/') ?>';

        // Check if Google Sign-In loads properly
        window.addEventListener('load', function() {
            setTimeout(function() {
                const googleSignIn = document.querySelector('.g_id_signin');

                // Silently check if Google Sign-In is present; avoid logging sensitive data
            }, 3000); // Wait 3 seconds for Google Sign-In to load
        });

        // FedCM usage disabled; rely on Google button instead

        // Traditional OAuth fallback (or let Google button handle it)
        function fallbackLogin() {
            // If Google Identity Services is present, let the user click the button.
            // Optionally, you can open a popup to Google OAuth as a strict fallback.
            // const url = 'https://accounts.google.com/o/oauth2/v2/auth?client_id=' + encodeURIComponent('<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>') + '&redirect_uri=' + encodeURIComponent(window.location.origin + '/google_oauth_callback.php') + '&response_type=token&scope=email%20profile';
            // window.open(url, '_blank', 'width=500,height=600');
        }


        function handleGoogleCredential(response) {
            // Do not log raw credentials; send minimal payload to backend for verification
            fetch(BACKEND_BASE_PATH + '/google_auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ credential: response.credential })
            })
            .then(function(r) {
                if (r.status === 410 || r.status === 503 || r.status === 500 || !r.ok) {
                    // Endpoint unavailable or backend error — fall back to the standard
                    // Google OAuth redirect flow. Use the direct backend URL because the
                    // OAuth callback is registered on the backend domain and cannot go
                    // through the same-origin /backend proxy.
                    window.location.href = BACKEND_DIRECT_URL + '/auth/google';
                    return null;
                }
                return r.json();
            })
            .then(function(data) {
                if (!data) return;
                if (data.success && data.requires_type_selection) {
                    // First-time login and role ambiguous: prompt for registrant type
                    const tempUserId = data.temp_user_id;
                    const suggested = (data.user_info && data.user_info.derived_type) ? data.user_info.derived_type : 'student';
                    // Persist pending context so modal can be restored if page reloads
                    try { sessionStorage.setItem('pending_role_selection', JSON.stringify({ tempUserId, suggested, ts: Date.now() })); } catch (_) {}
                    showRoleSelection(tempUserId, suggested);
                    return;
                }
                if (data.success || data.status === 'success') {
                    window.location.href = data.redirect || 'user-dashboard.php';
                } else {
                    showLoginError(data.message || 'Sign-in failed. Please try again.');
                }
            })
            .catch(function() {
                showLoginError('Sign-in failed. Please check your connection and try again.');
            });
        }

        function showLoginError(message) {
            let el = document.getElementById('google-login-error');
            if (!el) {
                el = document.createElement('div');
                el.id = 'google-login-error';
                el.style.cssText = 'margin-top:1rem;padding:.75rem 1rem;background:#fef2f2;border:1px solid #fca5a5;border-radius:.5rem;color:#b91c1c;font-size:.875rem;text-align:center;';
                const form = document.getElementById('googleLoginForm');
                if (form) form.insertAdjacentElement('afterend', el);
            }
            el.textContent = message;
        }



        // Debug function to check Google Sign-In status
        function checkGoogleSignInStatus() {
            return document.querySelector('.g_id_signin');
        }

        // Call debug function after page loads
        window.addEventListener('load', function() {
            setTimeout(checkGoogleSignInStatus, 2000);
            // If a pending role selection exists (from a previous step), auto-open the modal
            try {
                const q = new URLSearchParams(window.location.search);
                const stored = sessionStorage.getItem('pending_role_selection');
                if (stored) {
                    const obj = JSON.parse(stored);
                    if (obj && obj.tempUserId) {
                        showRoleSelection(obj.tempUserId, obj.suggested);
                    }
                } else if (q.get('requires_type_selection') === '1') {
                    // Only open if we have a pending tempUserId cached; otherwise, wait for next auth
                    const obj = stored ? JSON.parse(stored) : null;
                    if (obj && obj.tempUserId) {
                        showRoleSelection(obj.tempUserId, obj.suggested);
                    }
                }
            } catch (_) {}
        });

        // One-time role selection UI with ID validation
        function showRoleSelection(userId, suggested) {
            // Inject minimal styles for a clean look
            if (!document.getElementById('roleSelectStyles')) {
                const style = document.createElement('style');
                style.id = 'roleSelectStyles';
                style.textContent = `
                    .role-modal { box-shadow: 0 12px 30px rgba(0,0,0,.18); border-radius: 14px; padding: 22px; }
                    .role-modal h3 { margin: 0 0 6px; color: var(--primary-red); font-size: 20px; }
                    .role-modal p { margin: 0 0 14px; color: #444; font-size: 14px; }
                    .role-actions { display:flex; gap:.75rem; }
                    .role-btn { flex:1; padding: .8rem 1rem; border: 1px solid #e5e7eb; border-radius: 10px; background:#f8fafc; color:#111827; font-weight:600; cursor:pointer; transition: all .15s ease; }
                    .role-btn:hover, .role-btn:focus { background: var(--primary-red); color:#fff; border-color: var(--primary-red); outline:none; box-shadow: 0 0 0 3px rgba(208,0,0,.12); }
                `;
                document.head.appendChild(style);
            }
            const overlay = document.createElement('div');
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.background = 'rgba(0,0,0,0.5)';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.zIndex = '2000';

            const panel = document.createElement('div');
            panel.className = 'role-modal';
            panel.style.background = '#fff';
            panel.style.width = '95%';
            panel.style.maxWidth = '420px';

            panel.innerHTML = `
                <h3>Select Your Role</h3>
                <p>Please select your registrant type to complete your first-time login.</p>
                <div class="role-actions">
                    <button data-role="student" class="role-btn">Student</button>
                    <button data-role="staff" class="role-btn">Staff</button>
                </div>
                <div id="roleIdBlock" style="margin-top:12px; display:none;">
                    <input id="roleIdInput" type="text" placeholder="Enter your ID" style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:10px;">
                    <div id="roleHint" style="font-size:12px; color:#6b7280; margin-top:6px;"></div>
                    <div id="roleMsg" style="font-size:13px; margin-top:8px; display:none;"></div>
                    <button id="roleContinue" class="role-btn" style="margin-top:10px; background: var(--primary-red); color:#fff; border-color: var(--primary-red);">Continue</button>
                </div>
            `;

            overlay.appendChild(panel);
            document.body.appendChild(overlay);

            const roleIdBlock = panel.querySelector('#roleIdBlock');
            const roleIdInput = panel.querySelector('#roleIdInput');
            const roleHint = panel.querySelector('#roleHint');
            const roleMsg = panel.querySelector('#roleMsg');
            const continueBtn = panel.querySelector('#roleContinue');


            const rules = {
                student: /^\d{6}$/,                 // exactly 6 digits
                staff: /^[A-Za-z0-9]{5}$/            // exactly 5 alphanumeric (e.g., C1234)
            };

            let currentRole = null;

            const setHint = (role) => {
                if (role === 'student') {
                    roleHint.textContent = 'Enter 6-digit Student Reg No (e.g., 123456)';
                    roleIdInput.placeholder = 'Enter 6-digit Student Reg No';
                } else if (role === 'staff') {
                    roleHint.textContent = 'Enter 5-character Staff Reg No (alphanumeric, e.g., C1234)';
                    roleIdInput.placeholder = 'Enter 5-character Staff Reg No';
                }
            };

            panel.querySelectorAll('button[data-role]').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentRole = btn.getAttribute('data-role');
                    roleIdBlock.style.display = 'block';
                    roleMsg.style.display = 'none';
                    roleIdInput.value = '';
                    setHint(currentRole);
                    setTimeout(() => roleIdInput.focus(), 50);
                });
            });

            const trySubmit = () => {
                // Ensure we have a temp user id; try to recover from sessionStorage
                let effectiveUserId = userId;
                if (!effectiveUserId) {
                    try {
                        const pr = sessionStorage.getItem('pending_role_selection');
                        if (pr) {
                            const obj = JSON.parse(pr);
                            if (obj && obj.tempUserId) effectiveUserId = obj.tempUserId;
                        }
                    } catch (_) {}
                }
                if (!effectiveUserId) {
                    roleMsg.style.display = 'block';
                    roleMsg.style.color = '#991b1b';
                    roleMsg.textContent = 'Session expired. Please sign in again.';
                    return;
                }
                if (!currentRole) {
                    roleMsg.style.display = 'block';
                    roleMsg.style.color = '#991b1b';
                    roleMsg.textContent = 'Please select your role before continuing.';
                    return;
                }
                const role = currentRole;
                const idVal = (roleIdInput.value || '').trim();
                const regex = rules[role];
                const ok = regex.test(idVal);
                roleMsg.style.display = 'block';
                roleMsg.style.color = ok ? '#065f46' : '#991b1b';
                if (!ok) {
                    const msg = role === 'student'
                        ? 'You must enter a valid 6-digit Student Reg No before continuing.'
                        : role === 'staff'
                            ? 'You must enter a valid 5-character Staff ID (letters/numbers) before continuing.'
                            : 'You must enter a valid email address for Guest access before continuing.';
                    roleMsg.textContent = msg;
                } else {
                    roleMsg.textContent = 'Looks good.';
                }
                if (!ok) {
                    return; // do not proceed on invalid input
                }
                // Persist current role and identifier so we can restore on failure or reload
                try { sessionStorage.setItem('pending_role_selection_state', JSON.stringify({ role, identifier: idVal })); } catch (_) {}
                finalizeRole(effectiveUserId, role, overlay, roleMsg, { identifier: idVal });
            };

            continueBtn.addEventListener('click', trySubmit);
            roleIdInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); trySubmit(); } });
        }

        function finalizeRole(userId, role, overlay, roleMsgEl, extra = {}) {
            const showRoleError = (message) => {
                if (!roleMsgEl) {
                    showLoginError(message);
                    return;
                }
                roleMsgEl.style.display = 'block';
                roleMsgEl.style.color = '#991b1b';
                roleMsgEl.textContent = message;
            };

            const payload = Object.assign({ temp_user_id: userId, registrantType: role }, extra);
            fetch('/backend/finalize_role.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data && (data.success || data.status === 'success')) {
                    // Clear persisted pending context
                    try {
                        sessionStorage.removeItem('pending_role_selection');
                        sessionStorage.removeItem('pending_role_selection_state');
                    } catch (_) {}
                    if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
                    window.location.href = data.redirect || 'user-dashboard.php';
                } else {
                    showRoleError((data && (data.message || data.error)) || 'Failed to save selection. Please try again.');
                }
            })
            .catch(err => {
                showRoleError('Failed to save selection. Please try again.');
            });
        }
    </script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</body>
</html> 
