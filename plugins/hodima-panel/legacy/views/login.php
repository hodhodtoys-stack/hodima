<?php
/**
 * نمای ورود، ثبت‌نام و ورود با پیامک (منتقل‌شده از پنل قدیمی؛ بازنویسی: مرحله ۴ و ۵).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$auth = \Hodima\Panel\Legacy\Auth::instance();
$remaining_cooldown = 0;
if ($auth->view === 'otp_verify' && !empty($auth->input_mobile)) {
    $cooldown_end = get_transient('otp_cool_' . md5($auth->input_mobile));
    if ($cooldown_end) {
        $remaining_cooldown = max(0, $cooldown_end - time());
    }
}
?>

<!-- ========================================== -->
<!-- بخش UI/UX (بهینه‌سازی شده برای موبایل) -->
<!-- ========================================== -->


<div class="auth-page">
    <div id="up-toast-container"></div>

    <div class="auth-wrapper">
        <div class="auth-container">
            
            <!-- Branding Panel -->
            <div class="auth-branding-panel">
                <div class="brand-content">
                    <div class="logo">هدهدلی</div>
                    <h1>به دنیای امکانات<br>خوش آمدید</h1>
                    <p>برای دسترسی به پنل کاربری، دوره‌های آموزشی و پشتیبانی اختصاصی، وارد حساب کاربری خود شوید یا در کمتر از ۱ دقیقه ثبت‌نام کنید.</p>
                </div>
            </div>

            <!-- Form Panel -->
            <div class="auth-form-panel">
                
                <!-- فرم ورود -->
                <div id="form-login" class="auth-form-step <?php echo ($auth->view === 'login') ? 'active' : ''; ?>">
                    <h2 class="mobile-step-title">ورود به حساب</h2>
                    <div class="auth-header">
                        <h2>ورود به حساب</h2>
                        <p>خوشحالیم که دوباره می‌بینیمت! اطلاعاتت رو وارد کن.</p>
                    </div>
                    <form method="post" class="up-auth-form">
                        <?php wp_nonce_field('hodima_auth_action', 'hodima_auth_nonce'); ?>
                        <input type="text" name="up_website_url" class="up-honeypot" tabindex="-1">
                        
                        <div class="input-group">
                            <input type="text" name="mobile" class="auth-field numeric-only" placeholder="شماره موبایل (مثلا 0912...)" value="<?php echo esc_attr($auth->input_mobile); ?>" required pattern="^09[0-9]{9}$" autocomplete="username">
                            <span class="input-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2"></path><rect x="5" y="2" width="14" height="20" rx="2"></rect><path d="M12 18h.01"></path></svg></span>
                        </div>
                        
                        <div class="input-group">
                            <input type="password" name="password" id="login_pass" class="auth-field" placeholder="رمز عبور" required autocomplete="current-password">
                            <span class="input-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span>
                            <span class="toggle-password" data-target="login_pass"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></span>
                        </div>
                        
                        <label class="checkbox-group">
                             <input type="checkbox" name="remember_me" checked>
                             <span>مرا به خاطر بسپار</span>
                        </label>
                        
                        <button type="submit" name="login_with_password" class="btn-auth">ورود به پنل</button>
                    </form>
                    
                    <div class="divider">یا</div>
                    
                    <div class="auth-links">
                        <a href="javascript:void(0)" class="step-trigger" data-step="otp_request">ورود سریع با <strong>پیامک (بدون رمز)</strong></a>
                        <a href="javascript:void(0)" class="step-trigger" data-step="register">حساب ندارید؟ <strong>ثبت‌نام کنید</strong></a>
                    </div>
                </div>

                <!-- فرم ثبت‌نام -->
                <div id="form-register" class="auth-form-step <?php echo ($auth->view === 'register') ? 'active' : ''; ?>">
                    <h2 class="mobile-step-title">ساخت حساب جدید</h2>
                    <div class="auth-header">
                        <h2>ساخت حساب جدید</h2>
                        <p>فقط چند ثانیه تا عضویت فاصله داری.</p>
                    </div>
                    <form method="post" class="up-auth-form">
                        <?php wp_nonce_field('hodima_auth_action', 'hodima_auth_nonce'); ?>
                        <input type="text" name="up_website_url" class="up-honeypot" tabindex="-1">
                        
                        <div class="input-group">
                            <input type="text" name="reg_mobile" class="auth-field numeric-only" placeholder="شماره موبایل" value="<?php echo esc_attr($auth->input_mobile); ?>" required pattern="^09[0-9]{9}$" autocomplete="off">
                            <span class="input-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2"></path><rect x="5" y="2" width="14" height="20" rx="2"></rect><path d="M12 18h.01"></path></svg></span>
                        </div>
                        
                        <div class="input-group">
                            <input type="password" name="reg_password" id="reg_pass" class="auth-field" placeholder="رمز عبور (حداقل ۸ حرف)" required minlength="8" autocomplete="new-password">
                             <span class="input-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span>
                            <span class="toggle-password" data-target="reg_pass"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></span>
                        </div>
                        
                        <div class="input-group">
                            <input type="password" name="reg_password_conf" class="auth-field" placeholder="تکرار رمز عبور" required minlength="8" autocomplete="new-password">
                            <span class="input-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 5-5a5 5 0 0 1 5 5v4"></path></svg></span>
                        </div>
                        
                        <button type="submit" name="do_register" class="btn-auth">تکمیل ثبت‌نام</button>
                    </form>
                    <div class="auth-links" style="margin-top: 20px;">
                        <a href="javascript:void(0)" class="step-trigger" data-step="login">قبلاً عضو شده‌اید؟ <strong>وارد شوید</strong></a>
                    </div>
                </div>

                <!-- فرم درخواست OTP -->
                <div id="form-otp_request" class="auth-form-step <?php echo ($auth->view === 'otp_request') ? 'active' : ''; ?>">
                    <h2 class="mobile-step-title">ورود با پیامک</h2>
                    <div class="auth-header">
                        <h2>ورود با پیامک</h2>
                        <p>شماره موبایلت رو وارد کن تا کد تایید برات پیامک بشه.</p>
                    </div>
                    <form method="post" class="up-auth-form">
                        <?php wp_nonce_field('hodima_auth_action', 'hodima_auth_nonce'); ?>
                        <input type="text" name="up_website_url" class="up-honeypot" tabindex="-1">
                        
                        <div class="input-group">
                            <input type="text" name="otp_mobile" class="auth-field numeric-only" placeholder="شماره موبایل (09...)" value="<?php echo esc_attr($auth->input_mobile); ?>" required pattern="^09[0-9]{9}$" autocomplete="off">
                            <span class="input-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2"></path><rect x="5" y="2" width="14" height="20" rx="2"></rect></svg></span>
                        </div>
                        
                        <button type="submit" name="send_otp" class="btn-auth">ارسال کد تایید</button>
                    </form>
                    <div class="auth-links">
                        <a href="javascript:void(0)" class="step-trigger" data-step="login">بازگشت به <strong>ورود با رمز عبور</strong></a>
                    </div>
                </div>

                <!-- فرم تایید OTP -->
                <div id="form-otp_verify" class="auth-form-step <?php echo ($auth->view === 'otp_verify') ? 'active' : ''; ?>">
                    <h2 class="mobile-step-title">کد را وارد کنید</h2>
                    <div class="auth-header">
                        <h2>تایید شماره</h2>
                        <p>کد ۵ رقمی ارسال شده به <b style="color:var(--up-primary); direction:ltr; display:inline-block;"><?php echo esc_html($auth->input_mobile); ?></b> را وارد کنید.</p>
                    </div>
                    <form method="post" id="otp-verify-form" class="up-auth-form">
                        <?php wp_nonce_field('hodima_auth_action', 'hodima_auth_nonce'); ?>
                        <input type="text" name="up_website_url" class="up-honeypot" tabindex="-1">
                        
                        <!-- فیلد مخفی برای ارسال به PHP -->
                        <input type="hidden" name="otp_code" id="real_otp_code" required>
                        
                        <!-- 5 باکس برای وارد کردن کد -->
                        <div class="otp-inputs-container" id="otp-inputs-wrapper">
                            <input type="text" class="otp-box numeric-only" maxlength="1" autocomplete="off" autofocus>
                            <input type="text" class="otp-box numeric-only" maxlength="1" autocomplete="off">
                            <input type="text" class="otp-box numeric-only" maxlength="1" autocomplete="off">
                            <input type="text" class="otp-box numeric-only" maxlength="1" autocomplete="off">
                            <input type="text" class="otp-box numeric-only" maxlength="1" autocomplete="off">
                        </div>

                        <button type="submit" name="verify_otp" class="btn-auth" id="btn-verify-otp">تایید و ورود</button>
                    </form>
                    
                    <div class="timer-text" id="otp-timer-container">
                        ارسال مجدد کد تا <span id="otp-timer">00:00</span> دیگر
                    </div>
                    
                    <form method="post" id="resend-form" style="display:none; margin-top: 20px;">
                        <?php wp_nonce_field('hodima_auth_action', 'hodima_auth_nonce'); ?>
                        <button type="submit" name="resend_otp" class="resend-btn">ارسال مجدد کد به پیامک</button>
                    </form>
                    
                    <div class="auth-links">
                        <a href="javascript:void(0)" class="step-trigger" data-step="otp_request">شماره اشتباه است؟ <strong>تغییر شماره</strong></a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // Toast Logic
    const showToast = (text, typeClass) => { 
        const container = document.getElementById('up-toast-container'); if (!container) return; 
        const toast = document.createElement('div'); toast.className = `up-toast-msg ${typeClass}`; toast.innerText = text; 
        container.appendChild(toast); 
        requestAnimationFrame(() => toast.classList.add('show-popup')); 
        setTimeout(() => { toast.classList.remove('show-popup'); setTimeout(() => toast.remove(), 400); }, 4000); 
    };
    
    <?php if (!empty($auth->error)): ?> showToast(<?php echo json_encode($auth->error); ?>, 'up-alert-error'); <?php endif; ?>
    <?php if (!empty($auth->success)): ?> showToast(<?php echo json_encode($auth->success); ?>, 'up-alert-success'); <?php endif; ?>
    
    // Tab Switching
    document.querySelectorAll('.step-trigger').forEach(trigger => { 
        trigger.addEventListener('click', (e) => { 
            const stepName = e.target.closest('a').dataset.step; 
            document.querySelectorAll('.auth-form-step').forEach(f => f.classList.remove('active')); 
            const target = document.getElementById('form-' + stepName); 
            if (target) { 
                target.classList.add('active'); 
                const firstInput = target.querySelector('.auth-field') || target.querySelector('.otp-box'); 
                if(firstInput) setTimeout(() => firstInput.focus(), 150); 
            } 
        }); 
    });
    
    // Toggle Password Visibility
    const eyeSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>`;
    const eyeOffSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>`;
    document.querySelectorAll('.toggle-password').forEach(btn => { 
        btn.addEventListener('click', function() { 
            const input = document.getElementById(this.dataset.target); 
            if (input.type === "password") { input.type = "text"; this.innerHTML = eyeOffSvg; } 
            else { input.type = "password"; this.innerHTML = eyeSvg; } 
        }); 
    });

    // English Number Converter & Number Only Filter
    const filterNumbers = (el) => {
        const persianNumbers = [/۰/g, /۱/g, /۲/g, /۳/g, /۴/g, /۵/g, /۶/g, /۷/g, /۸/g, /۹/g]; 
        const arabicNumbers  = [/٠/g, /١/g, /٢/g, /٣/g, /٤/g, /٥/g, /٦/g, /٧/g, /٨/g, /٩/g]; 
        let val = el.value; 
        for(let i=0; i<10; i++) { val = val.replace(persianNumbers[i], i).replace(arabicNumbers[i], i); } 
        el.value = val.replace(/[^0-9]/g, '');
    };
    document.querySelectorAll('.numeric-only').forEach(input => { input.addEventListener('input', function() { filterNumbers(this); }); });

    // ==========================================
    // OTP 5-Box Logic (Enterprise Level UX)
    // ==========================================
    const otpBoxes = document.querySelectorAll('.otp-box');
    const hiddenOtpInput = document.getElementById('real_otp_code');
    
    if (otpBoxes.length > 0) {
        const updateHiddenInput = () => {
            let code = '';
            otpBoxes.forEach(box => code += box.value);
            hiddenOtpInput.value = code;
        };

        otpBoxes.forEach((box, index) => {
            // حرکت به جلو با زدن عدد
            box.addEventListener('input', function(e) {
                filterNumbers(this); // تبدیل حروف فارسی به انگلیسی
                if (this.value.length === 1) {
                    if (index < otpBoxes.length - 1) otpBoxes[index + 1].focus();
                    else document.getElementById('btn-verify-otp').focus(); // رفتن روی دکمه بعد از عدد آخر
                }
                updateHiddenInput();
            });

            // حرکت به عقب با Backspace
            box.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value === '') {
                    if (index > 0) {
                        otpBoxes[index - 1].focus();
                        otpBoxes[index - 1].value = ''; // پاک کردن باکس قبلی
                    }
                }
                setTimeout(updateHiddenInput, 10);
            });

            // پشتیبانی از Paste کردن کد 5 رقمی
            box.addEventListener('paste', function(e) {
                e.preventDefault();
                let pasteData = (e.clipboardData || window.clipboardData).getData('text');
                // تبدیل اعداد فارسی کپی شده به انگلیسی
                const persianNumbers = [/۰/g, /۱/g, /۲/g, /۳/g, /۴/g, /۵/g, /۶/g, /۷/g, /۸/g, /۹/g]; 
                for(let i=0; i<10; i++) { pasteData = pasteData.replace(persianNumbers[i], i); }
                pasteData = pasteData.replace(/[^0-9]/g, '').slice(0, 5); // فقط 5 عدد اول
                
                if(pasteData.length > 0) {
                    for(let i = 0; i < pasteData.length; i++) {
                        if(otpBoxes[i]) otpBoxes[i].value = pasteData[i];
                    }
                    updateHiddenInput();
                    if(pasteData.length === 5) document.getElementById('btn-verify-otp').focus();
                    else otpBoxes[pasteData.length].focus();
                }
            });
        });
        
        // اطمینان از مقدار گرفتن فیلد مخفی قبل از سابمیت فرم
        document.getElementById('otp-verify-form').addEventListener('submit', (e) => {
            updateHiddenInput();
            if(hiddenOtpInput.value.length < 5) {
                e.preventDefault();
                showToast('لطفا کد ۵ رقمی را کامل وارد کنید.', 'up-alert-error');
            }
        });
    }

    // OTP Timer
    <?php if ($auth->view === 'otp_verify' && $remaining_cooldown > 0): ?> 
        let timeLeft = <?php echo $remaining_cooldown; ?>; 
        const timerDisplay = document.getElementById('otp-timer'); 
        const timerContainer = document.getElementById('otp-timer-container'); 
        const resendForm = document.getElementById('resend-form'); 
        const updateTimer = () => { 
            if (timeLeft <= 0) { 
                clearInterval(countdown); 
                timerContainer.style.display = 'none'; 
                resendForm.style.display = 'block'; 
            } else { 
                let m = Math.floor(timeLeft / 60); let s = timeLeft % 60; 
                timerDisplay.innerText = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`; 
            } 
            timeLeft--; 
        }; 
        updateTimer(); 
        const countdown = setInterval(updateTimer, 1000); 
        setTimeout(() => { if(otpBoxes[0]) otpBoxes[0].focus(); }, 300); 
    <?php endif; ?>

    // Form Submit Loading Animation
    document.querySelectorAll('.up-auth-form').forEach(form => { 
        form.addEventListener('submit', function(e) { 
            if (this.checkValidity()) { 
                const btn = this.querySelector('.btn-auth'); 
                setTimeout(() => { 
                    btn.disabled = true; 
                    btn.innerHTML = `<svg style="width:22px;height:22px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg> کمی صبر کنید...`; 
                }, 50); 
            } 
        }); 
    });
    const style = document.createElement('style'); style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } }`; document.head.appendChild(style);
});
</script>
