<?php
// login.php
session_start();
require_once 'db.php';

$error = '';
$reg_error = '';
$reg_success = '';
$show_register = false;

/* ================= LOGIN ================= */
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username dan password wajib diisi!";
    } else {
        $stmt = $mysqli->prepare(
            "SELECT id, username, password, fullname, role 
             FROM users 
             WHERE username = ? 
             LIMIT 1"
        );

        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $row = $result->fetch_assoc();

                // LOGIN TANPA HASH (PLAINTEXT)
                if ($password === $row['password']) {
                    session_regenerate_id(true);
                    $_SESSION['user_id']  = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['fullname'] = $row['fullname'] ?: $row['username'];
                    $_SESSION['role']     = $row['role'] ?? 'user';

                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Password salah!";
                }
            } else {
                $error = "Username tidak ditemukan!";
            }
            $stmt->close();
        } else {
            $error = "Terjadi kesalahan sistem.";
        }
    }
}

/* ================= REGISTRASI ================= */
if (isset($_POST['register'])) {
    $show_register = true;

    $r_username = trim($_POST['r_username'] ?? '');
    $r_fullname = trim($_POST['r_fullname'] ?? '');
    $r_password = $_POST['r_password'] ?? '';
    $r_confirm  = $_POST['r_confirm'] ?? '';

    if ($r_username === '' || $r_password === '' || $r_confirm === '') {
        $reg_error = "Username, password, dan konfirmasi wajib diisi!";
    } elseif (strlen($r_username) < 3) {
        $reg_error = "Username minimal 3 karakter.";
    } elseif (strlen($r_password) < 6) {
        $reg_error = "Password minimal 6 karakter.";
    } elseif ($r_password !== $r_confirm) {
        $reg_error = "Konfirmasi password tidak cocok.";
    } else {
        // Cek username sudah ada atau belum
        $cek = $mysqli->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        if ($cek) {
            $cek->bind_param('s', $r_username);
            $cek->execute();
            $cek->store_result();

            if ($cek->num_rows > 0) {
                $reg_error = "Username sudah digunakan, silakan pilih yang lain.";
            } else {
                $cek->close();

                $role     = 'user';
                $fullname = ($r_fullname !== '') ? $r_fullname : $r_username;

                // Simpan plaintext (konsisten dengan login saat ini)
                $ins = $mysqli->prepare(
                    "INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)"
                );
                if ($ins) {
                    $ins->bind_param('ssss', $r_username, $r_password, $fullname, $role);
                    if ($ins->execute()) {
                        $reg_success = "Registrasi berhasil! Silakan login dengan akun baru Anda.";
                        $show_register = false;
                    } else {
                        $reg_error = "Gagal menyimpan data. Silakan coba lagi.";
                    }
                    $ins->close();
                } else {
                    $reg_error = "Terjadi kesalahan sistem saat menyimpan.";
                }
            }
        } else {
            $reg_error = "Terjadi kesalahan sistem.";
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login SCAT</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* ============ RESET ============ */
    *{box-sizing:border-box;margin:0;padding:0}

    /* ============ BACKGROUND MERAH ============ */
    body{
        font-family:'Segoe UI',Arial,Helvetica,sans-serif;
        min-height:100vh;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:20px;
        position:relative;
        overflow:hidden;
        background:
            radial-gradient(circle at 18% 18%, rgba(255,255,255,.18) 0%, transparent 38%),
            radial-gradient(circle at 82% 82%, rgba(0,0,0,.14) 0%, transparent 42%),
            linear-gradient(135deg,#FF6961 0%,#e0453c 55%,#b3241c 100%);
    }

    /* ==== STRIP AKSEN ATAS BAWAH ==== */
    .accent-strip{
        position:absolute;
        left:0; right:0;
        height:6px;
        background:rgba(0,0,0,.25);
        z-index:2;
    }
    .accent-strip.top{top:0}
    .accent-strip.bottom{bottom:0}

    /* ==== GRID HALUS DI BACKGROUND ==== */
    .blueprint-grid{
        position:absolute;
        inset:0;
        background-image:
            linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px);
        background-size:50px 50px;
        pointer-events:none;
        z-index:0;
        mask-image:radial-gradient(circle at center, black 30%, transparent 80%);
        -webkit-mask-image:radial-gradient(circle at center, black 30%, transparent 80%);
    }

    /* ==== ORNAMEN BUBBLE ==== */
    .bubble{
        position:absolute;
        border-radius:50%;
        background:rgba(255,255,255,.08);
        animation:bubbleFloat 9s ease-in-out infinite;
        pointer-events:none;
        z-index:0;
    }
    .bubble:nth-child(1){width:110px;height:110px;top:10%;right:18%;animation-delay:0s}
    .bubble:nth-child(2){width:70px;height:70px;bottom:12%;left:16%;animation-delay:2s}
    @keyframes bubbleFloat{
        0%,100%{transform:translateY(0)}
        50%{transform:translateY(-30px)}
    }

    /* ==== WRAPPER ==== */
    .login-wrap{
        position:relative;
        z-index:3;
        width:100%;
        max-width:430px;
        display:flex;
        flex-direction:column;
        align-items:center;
    }

    /* ==== KARTU ==== */
    .login-box{
        width:100%;
        background:#ffffff;
        padding:0 36px 30px;
        border-radius:16px;
        box-shadow:
            0 30px 70px rgba(0,0,0,.35),
            0 0 0 1px rgba(0,0,0,.04);
        text-align:center;
        position:relative;
        z-index:1;
        overflow:hidden;
        animation:cardIn .7s cubic-bezier(.22,1,.36,1);
    }
    @keyframes cardIn{
        from{opacity:0;transform:translateY(40px) scale(.95)}
        to{opacity:1;transform:translateY(0) scale(1)}
    }

    /* ==== BAND MERAH DI ATAS KARTU ==== */
    .card-band{
        height:6px;
        margin:0 -36px 26px;
        background:linear-gradient(90deg,#e0453c 0%,#ff6b62 50%,#e0453c 100%);
    }

    /* ==== BADGE ==== */
    .dept-badge{
        display:inline-flex;
        align-items:center;
        gap:6px;
        background:#e0453c;
        color:#fff;
        font-size:10.5px;
        font-weight:700;
        letter-spacing:1.5px;
        padding:6px 14px;
        border-radius:20px;
        margin-bottom:16px;
        text-transform:uppercase;
        box-shadow:0 4px 12px rgba(224,69,60,.35);
    }
    .dept-badge .dot{
        width:6px;height:6px;border-radius:50%;
        background:#fff;
        box-shadow:0 0 6px #fff;
        animation:pulseDot 2s infinite;
    }
    @keyframes pulseDot{
        0%,100%{opacity:1}
        50%{opacity:.4}
    }

    /* ==== LOGO ASLI ==== */
    .logo-container{
        margin:0 auto 14px;
        height:78px;
        display:flex;
        justify-content:center;
        align-items:center;
    }
    .logo-container img{
        height:68px;
        width:auto;
        max-width:220px;
        object-fit:contain;
    }

    /* ==== HEADING ==== */
    .login-box h2{
        color:#1a1a2e;
        font-size:23px;
        margin-bottom:6px;
        font-weight:700;
        letter-spacing:-.4px;
    }
    .subtitle{
        color:#6b7280;
        font-size:13px;
        margin-bottom:22px;
        line-height:1.5;
    }

    /* ==== DIVIDER ==== */
    .form-divider{
        display:flex;
        align-items:center;
        gap:10px;
        margin-bottom:18px;
        color:#9aa0ac;
        font-size:11px;
        text-transform:uppercase;
        letter-spacing:1.5px;
        font-weight:600;
    }
    .form-divider::before,
    .form-divider::after{
        content:'';
        flex:1;
        height:1px;
        background:linear-gradient(90deg,transparent,#e0e3e8,transparent);
    }

    /* ==== INPUT ==== */
    .input-group{
        position:relative;
        margin-bottom:14px;
        text-align:left;
    }
    .input-group > i{
        position:absolute;
        left:16px;
        top:50%;
        transform:translateY(-50%);
        color:#9aa0ac;
        font-size:14px;
        transition:color .2s;
        pointer-events:none;
    }
    .input-group input{
        width:100%;
        padding:14px 46px 14px 44px;
        border:1.5px solid #d5dae2;
        border-radius:8px;
        font-size:14px;
        background:#f8fafc;
        transition:all .25s;
        color:#1a1a2e;
    }
    .input-group input:focus{
        outline:none;
        border-color:#e0453c;
        background:#fff;
        box-shadow:0 0 0 3px rgba(224,69,60,.12);
    }
    .input-group input:focus ~ i{
        color:#e0453c;
    }
    .input-group input::placeholder{
        color:#a0a4b0;
    }
    .toggle-password{
        position:absolute;
        right:12px;
        top:50%;
        transform:translateY(-50%);
        background:none;
        border:none;
        color:#9aa0ac;
        cursor:pointer;
        padding:6px;
        font-size:14px;
        border-radius:4px;
        transition:all .2s;
    }
    .toggle-password:hover{
        color:#e0453c;
        background:rgba(224,69,60,.08);
    }

    /* ==== BUTTON MERAH ==== */
    .btn-login{
        width:100%;
        padding:15px;
        border:0;
        background:linear-gradient(135deg,#e0453c,#b3241c);
        color:#fff;
        border-radius:8px;
        cursor:pointer;
        font-size:14.5px;
        font-weight:700;
        letter-spacing:.5px;
        margin-top:10px;
        position:relative;
        overflow:hidden;
        transition:all .3s;
        box-shadow:0 6px 16px rgba(179,36,28,.35);
    }
    .btn-login::after{
        content:'';
        position:absolute;
        top:0;left:-100%;
        width:100%;height:100%;
        background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);
        transition:left .6s;
    }
    .btn-login:hover::after{left:100%}
    .btn-login:hover{
        transform:translateY(-2px);
        box-shadow:0 10px 22px rgba(179,36,28,.45);
    }
    .btn-login:active{transform:translateY(0)}

    /* ==== TOMBOL SEKUNDER (REGISTRASI) ==== */
    .btn-register-link{
        width:100%;
        padding:13px;
        border:1.5px solid #e0453c;
        background:#fff;
        color:#e0453c;
        border-radius:8px;
        cursor:pointer;
        font-size:14px;
        font-weight:700;
        letter-spacing:.5px;
        margin-top:10px;
        transition:all .25s;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
    }
    .btn-register-link:hover{
        background:#e0453c;
        color:#fff;
        transform:translateY(-2px);
        box-shadow:0 8px 18px rgba(224,69,60,.35);
    }

    /* ==== ERROR / SUCCESS ==== */
    .error{
        background:#fef2f2;
        color:#b00020;
        padding:12px 16px;
        border-radius:8px;
        margin-bottom:18px;
        font-size:13px;
        text-align:left;
        display:flex;
        align-items:center;
        gap:10px;
        border-left:3px solid #b00020;
        animation:shake .5s;
    }
    .error i{font-size:15px}
    .success{
        background:#f0fdf4;
        color:#15803d;
        padding:12px 16px;
        border-radius:8px;
        margin-bottom:18px;
        font-size:13px;
        text-align:left;
        display:flex;
        align-items:center;
        gap:10px;
        border-left:3px solid #15803d;
    }
    .success i{font-size:15px}
    @keyframes shake{
        0%,100%{transform:translateX(0)}
        25%{transform:translateX(-6px)}
        75%{transform:translateX(6px)}
    }

    /* ==== FOOTER ==== */
    .divider{
        height:1px;
        background:linear-gradient(90deg,transparent,#e0e3e8,transparent);
        margin:22px 0 12px;
    }
    .footer-text{
        text-align:center;
        font-size:11.5px;
        color:#8a8f99;
        line-height:1.7;
    }
    .footer-text strong{color:#e0453c;font-weight:700}
    .footer-text .hse-tag{
        display:inline-flex;
        align-items:center;
        gap:5px;
        color:#e0453c;
        font-weight:600;
        font-size:11px;
        text-transform:uppercase;
        letter-spacing:1px;
    }

    /* ============ MODAL REGISTRASI ============ */
    .modal-overlay{
        position:fixed;
        inset:0;
        background:rgba(15,23,42,.55);
        backdrop-filter:blur(3px);
        -webkit-backdrop-filter:blur(3px);
        z-index:50;
        display:none;
        align-items:center;
        justify-content:center;
        padding:20px;
        overflow-y:auto;
    }
    .modal-overlay.show{display:flex}

    .modal-box{
        width:100%;
        max-width:430px;
        background:#fff;
        border-radius:16px;
        box-shadow:0 30px 70px rgba(0,0,0,.4);
        overflow:hidden;
        animation:cardIn .4s cubic-bezier(.22,1,.36,1);
        position:relative;
        max-height:92vh;
        overflow-y:auto;
    }

    .modal-band{
        height:6px;
        background:linear-gradient(90deg,#e0453c 0%,#ff6b62 50%,#e0453c 100%);
    }

    .modal-head{
        padding:22px 28px 0;
        text-align:center;
        position:relative;
    }
    .modal-head h3{
        color:#1a1a2e;
        font-size:20px;
        font-weight:700;
        margin-bottom:4px;
    }
    .modal-head p{
        color:#6b7280;
        font-size:12.5px;
        margin-bottom:16px;
    }

    .modal-close{
        position:absolute;
        top:16px;
        right:16px;
        background:none;
        border:none;
        color:#9aa0ac;
        font-size:18px;
        cursor:pointer;
        width:32px;
        height:32px;
        border-radius:50%;
        display:flex;
        align-items:center;
        justify-content:center;
        transition:all .2s;
    }
    .modal-close:hover{
        background:#fef2f2;
        color:#e0453c;
    }

    .modal-body{
        padding:0 28px 26px;
        text-align:left;
    }

    .modal-body .btn-login{margin-top:6px}

    @media (max-width:480px){
        .login-wrap{max-width:100%}
        .login-box{padding:0 22px 24px;border-radius:12px}
        .card-band{margin:0 -22px 20px}
        .logo-container{height:64px}
        .logo-container img{height:54px}
        .login-box h2{font-size:20px}
        .modal-head{padding:20px 20px 0}
        .modal-body{padding:0 20px 22px}
    }
</style>
</head>
<body>

    <div class="accent-strip top"></div>
    <div class="accent-strip bottom"></div>
    <div class="blueprint-grid"></div>
    <div class="bubble"></div>
    <div class="bubble"></div>

    <div class="login-wrap">

        <!-- ==== LOGIN CARD ==== -->
        <div class="login-box">
            <div class="card-band"></div>

            <div class="dept-badge">
                <span class="dot"></span>
                HSE Department
            </div>

            <div class="logo-container">
                <img src="logo.png" alt="SCAT Logo">
            </div>

            <h2>Login SCAT</h2>
            <p class="subtitle">Sistem Investigasi Kecelakaan Kerja</p>

            <div class="form-divider">Sign In</div>

            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($reg_success): ?>
                <div class="success">
                    <i class="fas fa-circle-check"></i>
                    <span><?php echo htmlspecialchars($reg_success, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Username" required
                           value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="passwordInput"
                           placeholder="Password" required>
                    <button type="button" class="toggle-password" id="togglePassword"
                            aria-label="Tampilkan password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <button type="submit" name="login" class="btn-login">
                    <i class="fas fa-arrow-right-to-bracket"></i> Masuk
                </button>

                <button type="button" class="btn-register-link" id="openRegister">
                    <i class="fas fa-user-plus"></i> Registrasi Akun Baru
                </button>
            </form>

            <div class="divider"></div>

            <p class="footer-text">
                <strong>SCAT</strong> &middot; Sistem Investigasi Kecelakaan Kerja<br>
                <span class="hse-tag">
                    <i class="fas fa-helmet-safety"></i>
                    HSE Division
                </span>
                &middot; v1.0 &middot; &copy; <?= date('Y') ?>
            </p>
        </div>

    </div>

    <!-- ============ MODAL REGISTRASI ============ -->
    <div class="modal-overlay <?= $show_register ? 'show' : '' ?>" id="registerModal">
        <div class="modal-box">
            <div class="modal-band"></div>
            <div class="modal-head">
                <button type="button" class="modal-close" id="closeRegister" aria-label="Tutup">
                    <i class="fas fa-xmark"></i>
                </button>
                <h3><i class="fas fa-user-plus" style="color:#e0453c"></i> Registrasi Akun</h3>
                <p>Buat akun baru untuk mengakses SCAT</p>
            </div>

            <div class="modal-body">
                <?php if ($reg_error): ?>
                    <div class="error">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?php echo htmlspecialchars($reg_error, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="r_username" placeholder="Username" required
                               value="<?php echo htmlspecialchars($_POST['r_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="input-group">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="r_fullname" placeholder="Nama Lengkap (opsional)"
                               value="<?php echo htmlspecialchars($_POST['r_fullname'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="r_password" id="regPassword"
                               placeholder="Password (min. 6 karakter)" required>
                        <button type="button" class="toggle-password" id="toggleRegPassword"
                                aria-label="Tampilkan password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="r_confirm" id="regConfirm"
                               placeholder="Konfirmasi Password" required>
                    </div>

                    <button type="submit" name="register" class="btn-login">
                        <i class="fas fa-user-plus"></i> Daftar Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>

<script>
    /* Toggle password login */
    const toggle = document.getElementById('togglePassword');
    const pwd = document.getElementById('passwordInput');
    if (toggle && pwd) {
        toggle.addEventListener('click', function () {
            const isPwd = pwd.type === 'password';
            pwd.type = isPwd ? 'text' : 'password';
            this.innerHTML = isPwd
                ? '<i class="fas fa-eye-slash"></i>'
                : '<i class="fas fa-eye"></i>';
            this.setAttribute('aria-label', isPwd ? 'Sembunyikan password' : 'Tampilkan password');
        });
    }

    /* Toggle password registrasi */
    const toggleReg = document.getElementById('toggleRegPassword');
    const regPwd = document.getElementById('regPassword');
    if (toggleReg && regPwd) {
        toggleReg.addEventListener('click', function () {
            const isPwd = regPwd.type === 'password';
            regPwd.type = isPwd ? 'text' : 'password';
            this.innerHTML = isPwd
                ? '<i class="fas fa-eye-slash"></i>'
                : '<i class="fas fa-eye"></i>';
            this.setAttribute('aria-label', isPwd ? 'Sembunyikan password' : 'Tampilkan password');
        });
    }

    /* Modal registrasi */
    const modal = document.getElementById('registerModal');
    const openBtn = document.getElementById('openRegister');
    const closeBtn = document.getElementById('closeRegister');

    function openModal(){ modal.classList.add('show'); }
    function closeModal(){ modal.classList.remove('show'); }

    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    /* Klik overlay untuk menutup */
    modal.addEventListener('click', function(e){
        if (e.target === modal) closeModal();
    });

    /* ESC untuk menutup */
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && modal.classList.contains('show')) closeModal();
    });
</script>
</body>
</html>