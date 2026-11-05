<?php
/**
 * نمای پروفایل مشتری (منتقل‌شده از پنل قدیمی؛ ذخیره: legacy/profile.php؛ بازطراحی: مرحله ۵).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$current_user = wp_get_current_user();

if (!$current_user->ID) {
    echo '<div class="no-data">برای مشاهده این صفحه ابتدا وارد شوید.</div>';
    return;
}

// مقدارها از فیلدهای صورت‌حساب ووکامرس (Profile::fields؛ مرحله ۳)
$fields              = \Hodima\Panel\Profile::fields($current_user);
$user_phone          = $fields['mobile'];
$register_date       = \Hodima\Panel\show_date($current_user->user_registered, 'j F Y');
$user_address        = $fields['address'];
$user_postcode       = $fields['postcode'];
$display_name_value  = $fields['name'];
$is_default_name     = '' === $display_name_value;
$display_email_value = $fields['email'];
?>



<div class="profile-grid">

    <div class="profile-sidebar-card">
        <div class="user-info-brief">
            <p>پنل کاربری</p>
            <h3><?php echo $is_default_name ? 'کاربر جدید' : esc_html($current_user->display_name); ?></h3>
        </div>
        <div class="info-list">
            <div class="info-item">
                                <span>شماره تماس: <?php echo esc_html($user_phone); ?></span>
            </div>
            <div class="info-item">
                                <span>تاریخ عضویت: <?php echo esc_html($register_date); ?></span>
            </div>
        </div>
    </div>

    <div class="profile-main-content">

        <h2 class="profile-title">ویرایش اطلاعات کاربری</h2>

        <form id="profileForm">
            <?php wp_nonce_field('up_update_profile_action','up_profile_nonce'); ?>

            <div class="form-group">
                <label for="display_name">نام و نام‌خانوادگی <span class="required-star">*</span></label>
                <input type="text" id="display_name" class="form-control"
                    value="<?php echo esc_attr($display_name_value); ?>"
                    placeholder="نام خود را وارد کنید">
            </div>

            <div class="form-group">
                <label for="user_phone">شماره تماس</label>
                <input type="text" id="user_phone" class="form-control"
                    value="<?php echo esc_attr($user_phone); ?>" readonly>
            </div>

            <!-- فیلد ایمیل -->
            <div class="form-group">
                <label for="user_email">ایمیل (جهت دریافت فاکتور و اطلاع‌رسانی) <span class="required-star">*</span></label>
                <input type="email" id="user_email" class="form-control"
                    value="<?php echo esc_attr($display_email_value); ?>"
                    placeholder="example@gmail.com">
            </div>

            <div class="form-group">
                <label for="address">آدرس کامل <span class="required-star">*</span></label>
                <textarea id="address" class="form-control" rows="3"><?php echo esc_textarea($user_address); ?></textarea>
            </div>

            <div class="form-group">
                <label for="postcode">کد پستی <span class="required-star">*</span></label>
                <input type="text" id="postcode"
                    class="form-control"
                    value="<?php echo esc_attr($user_postcode); ?>"
                    maxlength="12" inputmode="numeric">
            </div>

            <hr>

            <h3 class="profile-subtitle">تغییر رمز عبور</h3>
            <p class="profile-hint">
                رمز عبور شما برای امنیت نمایش داده نمی‌شود. اگر قصد تغییر آن را دارید، رمز جدید را وارد کنید.
            </p>

            <div class="form-group">
                <label for="new_password">رمز عبور جدید</label>
                <input type="password" id="new_password" class="form-control" placeholder="********" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="confirm_password">تکرار رمز عبور جدید</label>
                <input type="password" id="confirm_password" class="form-control" placeholder="********" autocomplete="new-password">
            </div>

            <button type="submit" id="profileSubmit" class="submit-btn">
                ذخیره اطلاعات
            </button>

        </form>
    </div>
</div>

<script>
document.getElementById("profileForm").addEventListener("submit", function(e) {
    e.preventDefault();

    const name = document.getElementById("display_name").value.trim();
    const email = document.getElementById("user_email").value.trim();
    const address = document.getElementById("address").value.trim();
    // رقم فارسی/عربی ← لاتین و بدون فاصله/خط تیره (همان Profile::digits در سرور)
    const postcode = document.getElementById("postcode").value.trim()
        .replace(/[۰-۹]/g, (d) => String("۰۱۲۳۴۵۶۷۸۹".indexOf(d)))
        .replace(/[٠-٩]/g, (d) => String("٠١٢٣٤٥٦٧٨٩".indexOf(d)))
        .replace(/[\s-]/g, "");
    const pass = document.getElementById("new_password").value;
    const pass2 = document.getElementById("confirm_password").value;
    const nonce = document.getElementById("up_profile_nonce").value;

    // اعتبارسنجی نام (جلوگیری از ثبت شماره موبایل یا نام پیش‌فرض)
    if (name === "" || /^09[0-9]{9}$/.test(name) || name.includes("user_")) { 
        if(window.showToastMessage) window.showToastMessage("لطفاً نام و نام‌خانوادگی واقعی خود را وارد کنید", "up-alert-error"); 
        return; 
    }
    
    // اعتبارسنجی ایمیل
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (email === "" || !emailPattern.test(email)) {
        if(window.showToastMessage) window.showToastMessage("ایمیل معتبر وارد کنید", "up-alert-error"); 
        return; 
    }

    if (address.length < 10) { 
        if(window.showToastMessage) window.showToastMessage("آدرس کامل وارد کنید", "up-alert-error"); 
        return; 
    }
    if (!/^[0-9]{10}$/.test(postcode)) { 
        if(window.showToastMessage) window.showToastMessage("کد پستی باید ۱۰ رقم باشد", "up-alert-error"); 
        return; 
    }
    if (pass !== "" && (pass !== pass2 || pass.length < 8)) {
        if(window.showToastMessage) window.showToastMessage("رمز تازه باید دست‌کم ۸ حرف باشد و با تکرارش یکی باشد", "up-alert-error");
        return;
    }

    const data = new FormData();
    data.append("action", "hodima_panel_profile");
    data.append("display_name", name);
    data.append("user_email", email); 
    data.append("address", address);
    data.append("postcode", postcode);
    data.append("new_password", pass);
    data.append("confirm_password", pass2);
    data.append("nonce", nonce);

    // تغییر متن دکمه هنگام ارسال
    const submitBtn = document.getElementById("profileSubmit");
    const originalBtnText = submitBtn.innerText;
    submitBtn.innerText = "در حال ذخیره...";
    submitBtn.disabled = true;

    fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
        method: "POST",
        credentials: "same-origin",
        body: data
    })
    .then(r => r.json())
    .then(res => {
        submitBtn.innerText = originalBtnText;
        submitBtn.disabled = false;
        
        if (res.success) {
            if(window.showToastMessage) window.showToastMessage(res.data.message, "up-alert-success");
            
            // رفرش صفحه پس از موفقیت برای برداشته شدن قفل پنل
            setTimeout(() => {
                window.location.reload();
            }, 1500);
            
        } else {
            if(window.showToastMessage) window.showToastMessage(res.data.message, "up-alert-error");
        }
    })
    .catch(err => {
        submitBtn.innerText = originalBtnText;
        submitBtn.disabled = false;
        if(window.showToastMessage) window.showToastMessage("خطایی در ارتباط با سرور رخ داد.", "up-alert-error");
    });
});
</script>
