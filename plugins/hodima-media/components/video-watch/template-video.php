<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;
get_header();

if (have_posts()) :
    while (have_posts()) : the_post();
        $post_id = get_the_ID();
        $video_url = get_post_meta($post_id, '_hod_video_url', true);
        $video_thumb = get_post_meta($post_id, '_hod_video_thumbnail', true) ?: get_the_post_thumbnail_url($post_id, 'full');

        $views_display = number_format_i18n(class_exists('Video_Watch_Analytics') ? Video_Watch_Analytics::get_total_views((int) $post_id) : 0);

        $related_url   = get_post_meta($post_id, '_hod_related_product_url', true);
        $related_cards = get_post_meta($post_id, '_hod_related_links', true) ?: [];
        ?>

        <main class="hodima-page-wrapper">
            
            <?php
            /*
             * مسیر راهنما.
             * نسخه قبلی عنوان را اینجا داخل یک <h1> درون‌خطی می‌گذاشت، در
             * حالی که پایین‌تر یک <h1> دیگر هم بود: *دو* h1 در هر صفحه ویدئو.
             * حالا فقط یک h1 (عنوان اصلی) و اینجا یک عنصر معمولی با
             * aria-current. لینک «ویدئوها» همان آدرسی است که اسکیمای مسیر
             * راهنما استفاده می‌کند.
             */
            $videos_url = function_exists('hod_video_archive_url') ? hod_video_archive_url() : home_url('/videos/');
            ?>
            <nav class="section-breadcrumb" aria-label="<?php esc_attr_e('مسیر راهنما', 'hod-video'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('خانه', 'hod-video'); ?></a> /
                <a href="<?php echo esc_url($videos_url); ?>"><?php echo esc_html(function_exists('hod_video_archive_label') ? hod_video_archive_label() : __('ویدئوها', 'hod-video')); ?></a> /
                <span class="hs-breadcrumb-current" aria-current="page"><?php the_title(); ?></span>
            </nav>

            <section class="hodima-section-box hs-intro-section">
                
                <div class="hs-content-col">
                    <h1 class="hs-title"><?php the_title(); ?></h1>
                    <div class="hs-content-box">
                        <?php the_content(); ?>
                    </div>
                </div>

                <div class="hs-video-col">
                    <div class="hs-player" data-player-instance>
                        <div class="hs-stage hs-flex-center">
                            <!-- رفع باگ لود تنبل: قرار دادن مستقیم آدرس در src -->
                            <video class="hs-main-video" src="<?php echo esc_url($video_url); ?>" data-id="<?php echo esc_attr($post_id); ?>" data-title="<?php echo esc_attr(get_the_title()); ?>" playsinline preload="metadata" poster="<?php echo esc_url($video_thumb); ?>"></video>

                            <div class="hs-center-control hs-flex-center">
                                <button class="hs-play-toggle hs-play-btn hs-flex-center" aria-label="<?php esc_attr_e('پخش / توقف', 'hod-video'); ?>" tabindex="0">
                                    <span class="hs-play-icon">▶</span>
                                </button>
                            </div>
                        </div>

                        <div class="hs-bottombar">
                            <div class="hs-progress-wrap" tabindex="0" aria-label="<?php esc_attr_e('نوار پیشرفت ویدئو', 'hod-video'); ?>">
                                <div class="hs-progress-item"><div class="hs-progress-fill"></div></div>
                            </div>
                            
                            <div class="hs-bottom-controls">
                                <button class="hs-control-btn hs-play-pause-small" aria-label="<?php esc_attr_e('پخش / توقف', 'hod-video'); ?>" tabindex="0">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path class="hs-play-icon-path" d="M8 5v14l11-7z"/></svg>
                                </button>

                                <div class="hs-volume-container">
                                    <button class="hs-control-btn hs-mute-btn" aria-label="<?php esc_attr_e('صدا', 'hod-video'); ?>" tabindex="0">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                                    </button>
                                    <input type="range" class="hs-volume-slider" min="0" max="1" step="0.05" value="1" aria-label="<?php esc_attr_e('تنظیم صدا', 'hod-video'); ?>" tabindex="0">
                                </div>

                                <div class="hs-time-display">
                                    <span class="hs-current-time">0:00</span> / <span class="hs-duration">0:00</span>
                                </div>
                                
                                <div class="hs-spacer"></div>

                                <button class="hs-control-btn hs-pip-btn" aria-label="<?php esc_attr_e('تصویر در تصویر', 'hod-video'); ?>" tabindex="0">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><rect x="11" y="11" width="8" height="6"></rect></svg>
                                </button>

                                <div class="hs-more-options-container">
                                    <button class="hs-control-btn hs-speed-btn" aria-label="<?php esc_attr_e('سرعت پخش', 'hod-video'); ?>" tabindex="0">1x</button>
                                    <div class="hs-more-menu hs-speed-menu">
                                        <button class="hs-menu-option" data-speed="0.5">0.5x</button>
                                        <button class="hs-menu-option is-active" data-speed="1">1x (<?php esc_html_e('عادی', 'hod-video'); ?>)</button>
                                        <button class="hs-menu-option" data-speed="1.5">1.5x</button>
                                        <button class="hs-menu-option" data-speed="2">2x</button>
                                    </div>
                                </div>

                                <button class="hs-control-btn hs-fullscreen-btn" aria-label="<?php esc_attr_e('تمام‌صفحه', 'hod-video'); ?>" tabindex="0">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>
                                </button>
                                
                                <div class="hs-more-options-container">
                                    <button class="hs-control-btn hs-more-options-btn" aria-label="<?php esc_attr_e('گزینه‌های بیشتر', 'hod-video'); ?>" tabindex="0">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                                    </button>
                                    <div class="hs-more-menu">
                                        <a href="<?php echo esc_url($video_url); ?>" download class="hs-menu-option" target="_blank" rel="noopener noreferrer" tabindex="0">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hs-menu-icon"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                            <?php esc_html_e('دانلود ویدئو', 'hod-video'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hs-video-action-bar">
                        <?php
                        /*
                         * سه جعبه: محصول، بازدید، اشتراک. جعبه «تغییر صدا» حذف شد.
                         * جعبه محصول فقط وقتی لینک دارد رندر می‌شود؛ نسخه قبلی بدون
                         * لینک هم یک جعبه خالی می‌ساخت که یک‌سوم نوار را اشغال می‌کرد.
                         */
                        if ($related_url): ?>
                        <div class="hs-action-item hs-action-product">
                            <a href="<?php echo esc_url($related_url); ?>" class="hs-btn-primary" title="<?php esc_attr_e('مشاهده محصول', 'hod-video'); ?>" tabindex="0">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                <span class="hs-action-label"><?php esc_html_e('محصول', 'hod-video'); ?></span>
                            </a>
                        </div>
                        <?php endif; ?>

                        <div class="hs-action-item hs-action-stats">
                            <div class="hs-view-count" title="<?php esc_attr_e('بازدید', 'hod-video'); ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <span class="hs-live-view-count"><?php echo esc_html($views_display); ?></span>
                            </div>
                        </div>

                        <div class="hs-action-item hs-action-share">
                            <button class="hs-btn-outline hs-share-btn" aria-label="<?php esc_attr_e('اشتراک‌گذاری', 'hod-video'); ?>" tabindex="0">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                            </button>
                            
                            <div class="hs-action-menu hs-share-menu">
                                <button type="button" class="hs-share-link hs-copy-link" tabindex="0"><?php esc_html_e('کپی لینک', 'hod-video'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <?php // نمایش فرانت متن ترنسکریپت برداشته شد: همان مقداری که در vid-w-schema.php
                  // به‌صورت JSON-LD (فیلد transcript در Schema.org VideoObject) به گوگل سیگنال داده می‌شود کافی است. ?>

            <?php if(!empty($related_cards) && is_array($related_cards)): ?>
                <section class="hodima-section-box section-related-videos">
                    <h3 class="hs-related-section-title"><?php esc_html_e('ویدئوهای مرتبط', 'hod-video'); ?></h3>
                    <div class="hs-related-cards-wrapper">
                        <?php foreach($related_cards as $card): 
                            $cover_url = (isset($card['cover']) && !empty($card['cover'])) ? $card['cover'] : '';
                        ?>
                            <a href="<?php echo esc_url($card['url']); ?>" class="hs-related-card">
                                <div class="hs-related-cover-container">
                                    <?php if($cover_url): ?>
                                        <img src="<?php echo esc_url($cover_url); ?>" alt="<?php echo esc_attr($card['title']); ?>" class="hs-card-cover-img" loading="lazy" decoding="async" width="300" height="300">
                                    <?php else: ?>
                                        <div class="hs-related-icon-placeholder">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="hs-related-title" title="<?php echo esc_attr($card['title']); ?>"><?php echo esc_html($card['title']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        </main>
        <?php
    endwhile;
endif;
get_footer();
?>