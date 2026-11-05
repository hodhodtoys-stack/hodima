<?php
/**
 * نمای تیکت‌های پشتیبانی مشتری (منتقل‌شده از پنل قدیمی؛ پردازش فرم‌ها: legacy/support.php؛ بازطراحی: مرحله ۵).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Hodima\Panel\Page;
use function Hodima\Panel\show_date;
use function Hodima\Panel\table;

global $wpdb;
$user_id          = get_current_user_id();
$tables           = [ 'threads' => table( 'tickets' ), 'messages' => table( 'ticket_messages' ) ];
$current_page_url = Page::url( [ 'section' => 'support' ] );
// ===================================================================================
// ۲. دریافت اطلاعات برای نمایش (GET Requests) و صفحه‌بندی
// ===================================================================================

$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$per_page = 15;
$offset = ($paged - 1) * $per_page;

// دریافت تعداد کل تیکت‌ها برای صفحه‌بندی
$total_threads = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(id) FROM %i WHERE user_id = %d', $tables['threads'], $user_id));
$total_pages = ceil($total_threads / $per_page);

// واکشی لیست تیکت‌ها
$inbox_items = $wpdb->get_results($wpdb->prepare("
    SELECT id, reference_code, subject, last_message_preview, last_message_at, created_at, user_unread, status, priority
    FROM %i
    WHERE user_id = %d
    ORDER BY last_message_at DESC, id DESC
    LIMIT %d OFFSET %d
", $tables['threads'], $user_id, $per_page, $offset));

// دریافت اطلاعات تیکت انتخاب شده
$current_thread_id = isset($_GET['thread_id']) ? absint($_GET['thread_id']) : 0;
$current_thread = null;
$current_messages = [];

if ($current_thread_id > 0) {
    $current_thread = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d AND user_id = %d', $tables['threads'], $current_thread_id, $user_id));

    if ($current_thread) {
        $current_messages = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE ticket_id = %d ORDER BY id ASC', $tables['messages'], $current_thread_id));

        // اگر کاربر پیام نخوانده دارد، آن را صفر کن
        if ($current_thread->user_unread > 0) {
            $wpdb->update($tables['threads'], ['user_unread' => 0], ['id' => $current_thread_id]);
            $current_thread->user_unread = 0; 
        }
    }
}

// دیکشنری وضعیت‌ها
$status_dict = [
    'open' => ['label' => 'در انتظار پاسخ', 'color' => '#f59e0b'],
    'answered' => ['label' => 'پاسخ داده شده', 'color' => '#10b981'],
    'closed' => ['label' => 'بسته شده', 'color' => '#6b7280'],
];
$priority_dict = [
    'low' => ['label' => 'کم', 'color' => '#10b981'],
    'normal' => ['label' => 'متوسط', 'color' => '#3b82f6'],
    'high' => ['label' => 'زیاد', 'color' => '#ef4444'],
];

?>

<!-- =================================================================================== -->
<!-- ۳. رابط کاربری (HTML/CSS) -->
<!-- =================================================================================== -->


<div class="ts-app">
    
    <!-- سایدبار: لیست تیکت‌ها -->
    <div class="ts-sidebar">
        <div class="ts-sidebar-header">
            <h3>پشتیبانی</h3>
            <button class="ts-btn ts-btn-primary" onclick="document.getElementById('ts-modal').classList.add('active')">تیکت جدید +</button>
        </div>
        
        <div class="ts-ticket-list">
            <?php if (!empty($inbox_items)): ?>
                <?php foreach ($inbox_items as $item): 
                    $is_active = ($current_thread_id === (int)$item->id);
                    // حفظ پارامتر paged در url در صورت وجود
                    $url_args = ['thread_id' => $item->id];
                    if ($paged > 1) $url_args['paged'] = $paged;
                    $url = add_query_arg($url_args, $current_page_url);
                    
                    $status = $status_dict[$item->status] ?? ['label' => 'نامشخص', 'color' => '#000'];
                    $display_time = $item->last_message_at ? $item->last_message_at : $item->created_at;
                ?>
                    <a href="<?php echo esc_url($url); ?>" class="ts-item <?php echo $is_active ? 'active' : ''; ?>">
                        <div class="ts-item-header">
                            <span class="ts-item-title"><?php echo esc_html($item->subject ?: 'بدون موضوع'); ?></span>
                            <?php if ($item->user_unread > 0 && !$is_active): ?>
                                <span class="ts-badge ts-badge-red"><?php echo (int)$item->user_unread; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="ts-item-preview"><?php echo esc_html($item->last_message_preview); ?></div>
                        <div class="ts-item-footer">
                            <span class="ts-status-dot" style="background-color: <?php echo $status['color']; ?>" title="<?php echo esc_attr($status['label']); ?>"></span>
                            <span style="font-size:11px; color:#888;"><?php echo esc_html(show_date($display_time)); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ts-empty-state">تیکتی برای نمایش وجود ندارد.</div>
            <?php endif; ?>
        </div>

        <!-- صفحه‌بندی -->
        <?php if ($total_pages > 1): ?>
            <div class="ts-pagination">
                <?php if ($paged > 1): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $paged - 1, $current_page_url)); ?>" class="ts-btn ts-btn-sm">&laquo; قبلی</a>
                <?php else: ?>
                    <span class="ts-btn ts-btn-sm disabled">&laquo; قبلی</span>
                <?php endif; ?>
                
                <span class="ts-page-numbers">صفحه <?php echo $paged; ?> از <?php echo $total_pages; ?></span>
                
                <?php if ($paged < $total_pages): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $paged + 1, $current_page_url)); ?>" class="ts-btn ts-btn-sm">بعدی &raquo;</a>
                <?php else: ?>
                    <span class="ts-btn ts-btn-sm disabled">بعدی &raquo;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- بخش اصلی: چت و گفتگو -->
    <div class="ts-main">
        <?php if ($current_thread): 
            $t_status = $status_dict[$current_thread->status] ?? ['label' => $current_thread->status, 'color' => '#000'];
            $t_priority = $priority_dict[$current_thread->priority] ?? ['label' => 'متوسط', 'color' => '#000'];
        ?>
            <!-- هدر چت -->
            <div class="ts-chat-header">
                <div>
                    <h2 class="ts-chat-title">
                        <?php echo $current_thread->reference_code ? esc_html($current_thread->reference_code) : '#' . $current_thread->id; ?> - 
                        <?php echo esc_html($current_thread->subject); ?>
                    </h2>
                    <div class="ts-chat-meta">
                        <span style="color: <?php echo $t_status['color']; ?>">● <?php echo $t_status['label']; ?></span>
                        <span style="margin: 0 10px; color:#ccc;">|</span>
                        <span>اولویت: <span style="color: <?php echo $t_priority['color']; ?>"><?php echo $t_priority['label']; ?></span></span>
                    </div>
                </div>
            </div>

            <!-- بدنه چت (پیام‌ها) -->
            <div class="ts-chat-body" id="ts-chat-body">
                <?php foreach ($current_messages as $msg): 
                    $is_user = ($msg->sender_type === 'user');
                    $bubble_class = $is_user ? 'ts-bubble-user' : ($msg->sender_type === 'system' ? 'ts-bubble-system' : 'ts-bubble-admin');
                    $sender_name = $is_user ? 'شما' : ($msg->sender_type === 'system' ? 'سیستم' : 'پشتیبان');
                ?>
                    <div class="ts-message-wrapper <?php echo $is_user ? 'ts-right' : 'ts-left'; ?>">
                        <div class="ts-bubble <?php echo $bubble_class; ?>">
                            <div class="ts-msg-text"><?php echo nl2br(esc_html($msg->message)); ?></div>
                            <div class="ts-msg-time">
                                <?php echo $sender_name; ?> - <?php echo esc_html(show_date($msg->created_at)); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- فرم ارسال پاسخ -->
            <?php if ($current_thread->status !== 'closed'): ?>
                <div class="ts-chat-footer">
                    <form method="POST" action="<?php echo esc_url($current_page_url); ?>">
                        <input type="hidden" name="support_action" value="reply_to_ticket">
                        <input type="hidden" name="thread_id" value="<?php echo $current_thread->id; ?>">
                        <?php wp_nonce_field('reply_ticket_nonce', '_wpnonce_reply_ticket'); ?>
                        
                        <textarea name="message" class="ts-textarea" rows="3" placeholder="پاسخ خود را اینجا بنویسید..." required></textarea>
                        <div style="text-align: left; margin-top: 10px;">
                            <button type="submit" class="ts-btn ts-btn-success">ارسال پیام</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="ts-chat-footer" style="text-align: center; color: #888; background: #f3f4f6;">
                    این تیکت بسته شده است و امکان ارسال پاسخ وجود ندارد.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="ts-empty-state" style="height: 100%;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <p style="margin-top: 15px;">برای مشاهده گفتگو، یک تیکت را از منوی کناری انتخاب کنید.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- مودال ایجاد تیکت -->
<div id="ts-modal" class="ts-modal-overlay">
    <div class="ts-modal-box">
        <div class="ts-modal-header">
            <h3>ایجاد تیکت جدید</h3>
            <span class="ts-close-modal" onclick="document.getElementById('ts-modal').classList.remove('active')">&times;</span>
        </div>
        <div class="ts-modal-body">
            <form method="POST" action="<?php echo esc_url($current_page_url); ?>">
                <input type="hidden" name="support_action" value="create_ticket">
                <?php wp_nonce_field('create_ticket_nonce', '_wpnonce_create_ticket'); ?>
                
                <div class="ts-form-row">
                    <div class="ts-form-group">
                        <label>موضوع تیکت <span style="color:red">*</span></label>
                        <input type="text" name="subject" class="ts-input" required>
                    </div>
                </div>

                <!-- اضافه شدن ردیف دپارتمان و اولویت -->
                <div class="ts-form-row">
                    <div class="ts-form-group">
                        <label>دپارتمان</label>
                        <select name="department" class="ts-input">
                            <?php foreach (\Hodima\Panel\ticket_departments() as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ts-form-group">
                        <label>اولویت</label>
                        <select name="priority" class="ts-input">
                            <option value="normal">متوسط</option>
                            <option value="high">زیاد</option>
                            <option value="low">کم</option>
                        </select>
                    </div>
                </div>

                <div class="ts-form-group">
                    <label>متن پیام <span style="color:red">*</span></label>
                    <textarea name="message" class="ts-textarea" rows="5" required></textarea>
                </div>

                <div style="text-align: left;">
                    <button type="submit" class="ts-btn ts-btn-primary">ثبت تیکت</button>
                </div>
            </form>
        </div>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    // اسکرول اتوماتیک به آخرین پیام
    const chatBody = document.getElementById('ts-chat-body');
    if (chatBody) {
        chatBody.scrollTop = chatBody.scrollHeight;
    }
    
    // بستن مودال با کلیک در بیرون کادر
    const modal = document.getElementById('ts-modal');
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });

    // جلوگیری از ارسال تکراری فرم‌ها
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            if(btn) {
                setTimeout(() => {
                    btn.disabled = true;
                    btn.innerText = 'در حال ارسال...';
                }, 0);
            }
        });
    });
});
</script>
