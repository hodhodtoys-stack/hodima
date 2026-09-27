<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

// ==========================================
// 1. اضافه کردن دراپ‌داون فیلتر وضعیت سئو
// ==========================================

// الف: اضافه کردن فیلتر برای پست‌ها (نوشته، برگه، سیستم فروشگاهی)
add_action('restrict_manage_posts', 'seobox_add_post_index_filter');
function seobox_add_post_index_filter($post_type = '') {
    global $typenow;
    $current_type = !empty($post_type) ? $post_type : $typenow;

    if (!in_array($current_type, seobox_post_types(), true)) return;

    $selected = isset($_GET['seobox_index_filter']) ? sanitize_key( wp_unslash( (string) $_GET['seobox_index_filter'] ) ) : '';
    ?>
    <select name="seobox_index_filter" id="seobox_index_filter">
        <option value=""><?php echo esc_html( 'همه وضعیت‌های سئو' ); ?></option>
        <option value="index" <?php selected($selected, 'index'); ?>><?php echo esc_html( 'ایندکس‌ها (Index)' ); ?></option>
        <option value="noindex" <?php selected($selected, 'noindex'); ?>><?php echo esc_html( 'نوایندکس‌ها (Noindex)' ); ?></option>
    </select>
    <?php
}

// ب: اضافه کردن فیلتر برای تکسونومی‌ها (دسته‌بندی، برچسب)
add_action('restrict_manage_terms', 'seobox_add_term_index_filter', 10, 2);
function seobox_add_term_index_filter($tax_obj, $taxonomy = '') {
    if (!in_array($taxonomy, seobox_taxonomies(), true)) return;

    $selected = isset($_GET['seobox_index_filter']) ? sanitize_key( wp_unslash( (string) $_GET['seobox_index_filter'] ) ) : '';
    ?>
    <select name="seobox_index_filter" id="seobox_index_filter">
        <option value=""><?php echo esc_html( 'همه وضعیت‌های سئو' ); ?></option>
        <option value="index" <?php selected($selected, 'index'); ?>><?php echo esc_html( 'ایندکس‌ها (Index)' ); ?></option>
        <option value="noindex" <?php selected($selected, 'noindex'); ?>><?php echo esc_html( 'نوایندکس‌ها (Noindex)' ); ?></option>
    </select>
    <?php
}

// ==========================================
// 2. اعمال فیلتر روی کوئری اصلی
// ==========================================

// الف: اعمال کوئری روی پست‌ها
add_action('parse_query', 'seobox_filter_posts_by_index_status');
function seobox_filter_posts_by_index_status($query) {
    global $pagenow, $typenow;
    
    if ('edit.php' === $pagenow && $query->is_main_query()) {
        $current_type = $typenow ?: ($query->get('post_type') ?: 'post');
        
        if (in_array($current_type, seobox_post_types(), true) && isset($_GET['seobox_index_filter']) && '' !== $_GET['seobox_index_filter']) {
            
            $filter_value = sanitize_key( wp_unslash( (string) $_GET['seobox_index_filter'] ) );
            $meta_query = $query->get('meta_query') ?: [];

            if ('noindex' === $filter_value) {
                $meta_query[] = [
                    'key'     => '_seobox_robots',
                    'value'   => '"noindex"', 
                    'compare' => 'LIKE'
                ];
            } elseif ('index' === $filter_value) {
                $meta_query[] = [
                    'relation' => 'OR',
                    ['key' => '_seobox_robots', 'compare' => 'NOT EXISTS'],
                    ['key' => '_seobox_robots', 'value' => '"noindex"', 'compare' => 'NOT LIKE']
                ];
            }

            $query->set('meta_query', $meta_query);
        }
    }
}

// ب: اعمال کوئری روی تکسونومی‌ها
add_action('pre_get_terms', 'seobox_filter_terms_by_index_status');
function seobox_filter_terms_by_index_status($query) {
    global $pagenow;
    
    if ('edit-tags.php' === $pagenow && isset($_GET['seobox_index_filter']) && '' !== $_GET['seobox_index_filter']) {
        $taxonomy = sanitize_key( wp_unslash( (string) ( $_GET['taxonomy'] ?? '' ) ) );

        /*
         * فقط کوئری جدول فهرست. نسخه قبلی روی *هر* get_terms این صفحه اعمال
         * می‌شد — از جمله منوی «دسته والد» فرم افزودن، که با فعال بودن فیلتر
         * فقط دسته‌های noindex را نشان می‌داد.
         */
        // جدول فهرست «number» (تعداد در صفحه) دارد و شمارش صفحه‌بندی
        // «fields => count» است؛ منوی کشویی والد هیچ‌کدام را ندارد.
        $is_list_query  = ! empty( $query->query_vars['number'] );
        $is_count_query = 'count' === ( $query->query_vars['fields'] ?? '' );
        if ( ! $is_list_query && ! $is_count_query ) {
            return;
        }
        
        if (in_array($taxonomy, seobox_taxonomies(), true)) {
            $filter_value = sanitize_key( wp_unslash( (string) $_GET['seobox_index_filter'] ) );
            $meta_query = $query->query_vars['meta_query'] ?? [];

            if ('noindex' === $filter_value) {
                $meta_query[] = [
                    'key'     => '_seobox_robots',
                    'value'   => '"noindex"', 
                    'compare' => 'LIKE'
                ];
            } elseif ('index' === $filter_value) {
                $meta_query[] = [
                    'relation' => 'OR',
                    ['key' => '_seobox_robots', 'compare' => 'NOT EXISTS'],
                    ['key' => '_seobox_robots', 'value' => '"noindex"', 'compare' => 'NOT LIKE']
                ];
            }

            $query->query_vars['meta_query'] = $meta_query;
        }
    }
}

// ==========================================
// 3. اضافه کردن ستون وضعیت سئو
// ==========================================
function seobox_add_robots_column($columns) {
    if (is_array($columns)) {
        $columns['seobox_status'] = 'وضعیت سئو';
    }
    return $columns;
}

// اعمال روی نوشته، برگه، سیستم فروشگاهی
$seobox_post_types = seobox_post_types();
foreach ($seobox_post_types as $pt) {
    add_filter("manage_edit-{$pt}_columns", 'seobox_add_robots_column');
    add_action("manage_{$pt}_posts_custom_column", 'seobox_render_posts_robots_column', 10, 2);
}

// اعمال روی دسته‌بندی، برچسب، دسته‌بندی محصول
$seobox_taxonomies = seobox_taxonomies();
foreach ($seobox_taxonomies as $tax) {
    add_filter("manage_edit-{$tax}_columns", 'seobox_add_robots_column');
    add_filter("manage_{$tax}_custom_column", 'seobox_render_terms_robots_column', 10, 3);
}

// ==========================================
// 4. نمایش محتوای ستون (بج‌های گرافیکی)
// ==========================================

// الف: نمایش برای پست‌ها (نیاز به echo دارد)
function seobox_render_posts_robots_column($column, $post_id) {
    if ('seobox_status' === $column) {
        $robots = get_post_meta((int)$post_id, '_seobox_robots', true);
        $is_noindex = is_array($robots) && in_array('noindex', $robots, true);

        if ($is_noindex) {
            echo '<span class="seobox-badge seobox-badge--noindex">Noindex</span>';
        } else {
            echo '<span class="seobox-badge seobox-badge--index">Index</span>';
        }
    }
}

// ب: نمایش برای تکسونومی‌ها (نیاز به return دارد)
function seobox_render_terms_robots_column($content, $column_name, $term_id) {
    if ('seobox_status' === $column_name) {
        $robots = get_term_meta((int)$term_id, '_seobox_robots', true);
        $is_noindex = is_array($robots) && in_array('noindex', $robots, true);

        if ($is_noindex) {
            $content = '<span class="seobox-badge seobox-badge--noindex">Noindex</span>';
        } else {
            $content = '<span class="seobox-badge seobox-badge--index">Index</span>';
        }
    }
    return $content;
}