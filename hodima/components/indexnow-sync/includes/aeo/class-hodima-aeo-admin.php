<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

final class Hodima_AEO_Admin {

    public static function init(): void {
        add_action( 'add_meta_boxes', [ __CLASS__, 'meta_box' ] );
        add_action( 'save_post', [ __CLASS__, 'save_post_meta' ] );

        foreach ( [ 'category', 'product_cat' ] as $tax ) {
            add_action( "{$tax}_edit_form_fields", [ __CLASS__, 'tax_edit_ui' ] );
            add_action( "edited_{$tax}", [ __CLASS__, 'save_tax_meta' ] );
        }
    }

    public static function meta_box(): void {
        foreach ( [ 'post', 'page', 'product' ] as $screen ) {
            add_meta_box( 'hodima_aeo', 'پنل AEO * GEO', [ __CLASS__, 'post_ui' ], $screen, 'normal', 'high' );
        }
    }

    private static function render_ui_fields( int $object_id, string $object_type ): void {
        $get_meta = $object_type === 'term' ? fn($k) => get_term_meta($object_id, $k, true) : fn($k) => get_post_meta($object_id, $k, true);

        // داده‌های جهانی (انگلیسی)
        $en_entity          = (string) $get_meta('_h_ai_en_entity');
        $en_aliases         = (string) $get_meta('_h_ai_en_aliases');
        $en_synonyms        = (string) $get_meta('_h_ai_en_synonyms');
        $en_context         = (string) $get_meta('_h_ai_en_context');
        $en_usecases        = (string) $get_meta('_h_ai_en_usecases');
        $en_audience        = (string) $get_meta('_h_ai_en_audience');
        $en_comparison      = (string) $get_meta('_h_ai_en_comparison');
        $en_faqs            = (string) ( $get_meta('_h_ai_en_faqs') ?: '[]' );
        $en_prompts         = (string) $get_meta('_h_ai_en_prompts');
        $en_specs           = (string) ( $get_meta('_h_ai_en_specs') ?: '[]' ); 
        $en_embedding       = (string) $get_meta('_h_ai_en_embedding_summary'); 

        // داده‌های محلی (فارسی)
        $fa_entities        = (string) $get_meta('_h_ai_entities');
        $fa_aliases         = (string) $get_meta('_h_ai_fa_aliases');
        $fa_context         = (string) $get_meta('_h_ai_text');
        $fa_usecases        = (string) $get_meta('_h_ai_usecases');
        $fa_audience        = (string) $get_meta('_h_ai_audience');
        $fa_comparison      = (string) $get_meta('_h_ai_comparison');
        $fa_faqs            = (string) ( $get_meta('_h_ai_faqs') ?: '[]' );
        $fa_prompts         = (string) $get_meta('_h_ai_prompts');
        $fa_embedding       = (string) $get_meta('_h_ai_fa_embedding_summary'); 

        // انتخاب‌گر مدل فروش
        $biz_model          = (string) $get_meta('_h_ai_biz_model') ?: 'both';

        // داده‌های تجاری و B2B (تایر A)
        $fa_price_t1        = (string) $get_meta('_h_ai_fa_price_t1');
        $fa_sale_unit       = (string) $get_meta('_h_ai_fa_sale_unit');
        $en_sale_unit       = (string) $get_meta('_h_ai_en_sale_unit');
        $fa_price_basis     = (string) $get_meta('_h_ai_fa_price_basis') ?: 'کیلوگرم';
        $en_price_basis     = (string) $get_meta('_h_ai_en_price_basis') ?: 'kg';
        $fa_packaging       = (string) $get_meta('_h_ai_fa_packaging');
        $en_packaging       = (string) $get_meta('_h_ai_en_packaging');
        $fa_moq             = (string) $get_meta('_h_ai_fa_moq');
        $en_moq             = (string) $get_meta('_h_ai_en_moq');

        // داده‌های تجاری و B2B (تایر B)
        $fa_price_t2        = (string) $get_meta('_h_ai_fa_price_t2');
        $fa_sale_unit_t2    = (string) $get_meta('_h_ai_fa_sale_unit_t2');
        $en_sale_unit_t2    = (string) $get_meta('_h_ai_en_sale_unit_t2');
        $fa_price_basis_t2  = (string) $get_meta('_h_ai_fa_price_basis_t2') ?: 'بسته';
        $en_price_basis_t2  = (string) $get_meta('_h_ai_en_price_basis_t2') ?: 'Pack';
        $fa_packaging_t2    = (string) $get_meta('_h_ai_fa_packaging_t2');
        $en_packaging_t2    = (string) $get_meta('_h_ai_en_packaging_t2');
        $fa_moq_t2          = (string) $get_meta('_h_ai_fa_moq_t2');
        $en_moq_t2          = (string) $get_meta('_h_ai_en_moq_t2');

        $main_title = '';
        if ( $object_type === 'term' ) {
            $term = get_term($object_id);
            if ($term && !is_wp_error($term)) $main_title = $term->name;
        } else {
            $main_title = get_the_title($object_id);
        }

        $vid_url      = (string) $get_meta('_h_ai_vid_url');  
        $en_vid_title = (string) $get_meta('_h_ai_en_vid_title');
        $fa_vid_title = (string) $get_meta('_h_ai_fa_vid_title');
        $pod_url      = (string) $get_meta('_h_ai_pod_url');  
        $en_pod_title = (string) $get_meta('_h_ai_en_pod_title');
        $fa_pod_title = (string) $get_meta('_h_ai_fa_pod_title');

        // محدودیت گزینه‌ها به ۴ مورد درخواستی شما
        $units_fa = ['کیلوگرم', 'کارتن', 'بسته', 'جین'];
        $units_en = ['kg', 'Carton', 'Pack', 'Dozen'];
        ?>
        <div style="background:#f8fafc; padding:20px; direction:rtl; text-align:right; border-radius:4px; border:1px solid #c1c9ec; font-family: 'Vazirmatn', sans-serif;">

            <div style="background:#ffffff; padding:15px; border:1px solid #c1c9ec; border-right:4px solid #25316a; border-radius:8px; margin-bottom:20px;">
                <h4 style="margin:0 0 15px 0; padding-bottom:5px; border-bottom:1px solid #c1c9ec; color:#25316a;">۱. مفاهیم بنیادین (Core Semantics)</h4>
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:50%; padding:10px; border-left:1px solid #c1c9ec;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">موجودیت اصلی</label>
                            <input type="text" disabled value="<?php echo esc_attr($main_title ?: 'نام محصول/نوشته'); ?>" style="width:100%; padding:8px; border:1px solid #c1c9ec; border-radius:4px; background:#f1f5f9; color:#607bbd; cursor:not-allowed;">
                        </td>
                        <td style="width:50%; padding:10px;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; direction:ltr; text-align:left;">Global Entity Name</label>
                            <input type="text" name="h_ai_en_entity" value="<?php echo esc_attr($en_entity); ?>" placeholder="e.g. Mini Hair Ties" style="width:100%; padding:8px; direction:ltr; border:1px solid #c1c9ec; border-radius:4px;">
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="width:50%; padding:10px; border-left:1px solid #c1c9ec; background:#f4f6f8;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">نام‌های مستعار</label>
                            <input type="text" name="h_ai_fa_aliases" value="<?php echo esc_attr($fa_aliases); ?>" placeholder="مثال: کش چهل گیس، کش مو ریز" style="width:100%; padding:8px; border:1px solid #c1c9ec; border-radius:4px;">
                        </td>
                        <td style="width:50%; padding:10px; background:#f4f6f8;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; direction:ltr; text-align:left;">Aliases</label>
                            <input type="text" name="h_ai_en_aliases" value="<?php echo esc_attr($en_aliases); ?>" placeholder="e.g. Mini Elastic Bands, Clear Hair Elastics" style="width:100%; padding:8px; direction:ltr; border:1px solid #c1c9ec; border-radius:4px;">
                        </td>
                    </tr>

                    <tr>
                        <td style="width:50%; padding:10px; border-left:1px solid #c1c9ec;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">هدف جستجو</label>
                            <input type="text" name="h_ai_entities" value="<?php echo esc_attr($fa_entities); ?>" placeholder="مثال: خرید عمده کش چهل‌گیس، پخش کش مو" style="width:100%; padding:8px; border:1px solid #c1c9ec; border-radius:4px;">
                        </td>
                        <td style="width:50%; padding:10px;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; direction:ltr; text-align:left;">Search Intent</label>
                            <input type="text" name="h_ai_en_synonyms" value="<?php echo esc_attr($en_synonyms); ?>" placeholder="e.g. Wholesale TPU Elastics, Buy bulk hair ties" style="width:100%; padding:8px; direction:ltr; border:1px solid #c1c9ec; border-radius:4px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px; border-left:1px solid #c1c9ec;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">زمینه معنایی</label>
                            <textarea name="h_ai_text" style="width:100%; height:80px; padding:8px; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($fa_context); ?></textarea>
                        </td>
                        <td style="padding:10px;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; direction:ltr; text-align:left;">Semantic Context</label>
                            <textarea name="h_ai_en_context" style="width:100%; height:80px; padding:8px; font-family:monospace; direction:ltr; text-align:left; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($en_context); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px; border-left:1px solid #c1c9ec;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">کاربردها</label>
                            <textarea name="h_ai_usecases" style="width:100%; height:60px; padding:8px; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($fa_usecases); ?></textarea>
                        </td>
                        <td style="padding:10px;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; direction:ltr; text-align:left;">Use Cases</label>
                            <textarea name="h_ai_en_usecases" style="width:100%; height:60px; padding:8px; direction:ltr; text-align:left; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($en_usecases); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px; border-left:1px solid #c1c9ec;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">مناسب برای</label>
                            <textarea name="h_ai_audience" style="width:100%; height:60px; padding:8px; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($fa_audience); ?></textarea>
                        </td>
                        <td style="padding:10px;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; direction:ltr; text-align:left;">Target Audience</label>
                            <textarea name="h_ai_en_audience" style="width:100%; height:60px; padding:8px; direction:ltr; text-align:left; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($en_audience); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px; border-left:1px solid #c1c9ec;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">مقایسه</label>
                            <textarea name="h_ai_comparison" style="width:100%; height:60px; padding:8px; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($fa_comparison); ?></textarea>
                        </td>
                        <td style="padding:10px;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; direction:ltr; text-align:left;">Comparison</label>
                            <textarea name="h_ai_en_comparison" style="width:100%; height:60px; padding:8px; direction:ltr; text-align:left; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($en_comparison); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="padding:10px; background:#f4f6f8; border-top:1px solid #c1c9ec; border-left:1px solid #c1c9ec;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">خلاصه بُرداری / Embedding Summary</label>
                            <p style="font-size:12px; color:#607bbd; margin:0 0 8px 0;">یک پاراگراف چکیده (مخصوص دیتابیس‌های برداری) از کل ماهیت محصول، سایزها، و کاربرد بنویسید.</p>
                            <textarea name="h_ai_fa_embedding_summary" style="width:100%; height:80px; padding:8px; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($fa_embedding); ?></textarea>
                        </td>
                        <td style="padding:10px; background:#f4f6f8; border-top:1px solid #c1c9ec;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; direction:ltr; text-align:left;">Embedding Summary</label>
                            <p style="font-size:12px; color:#607bbd; margin:0 0 8px 0; direction:ltr; text-align:left;">A short, dense paragraph summarizing the entity for Vector DBs.</p>
                            <textarea name="h_ai_en_embedding_summary" style="width:100%; height:80px; padding:8px; direction:ltr; text-align:left; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($en_embedding); ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>

            <?php if ( $object_type !== 'term' ) : ?>
            <div style="background:#ffffff; padding:15px; border:1px solid #c1c9ec; border-right:4px solid #607bbd; border-radius:8px; margin-bottom:20px;">
                <h4 style="margin:0 0 15px 0; padding-bottom:5px; border-bottom:1px solid #c1c9ec; color:#25316a;">۲. اطلاعات تجاری و فروش (B2B Commercial Data)</h4>
                
                <div style="background:#f4f6f8; padding:10px 15px; border-radius:6px; margin-bottom:15px; border:1px solid #c1c9ec; display:flex; align-items:center; gap:20px;">
                    <label style="font-weight:bold; color:#25316a;">نوع مدل فروش</label>
                    <label style="cursor:pointer; color:#25316a;"><input type="radio" name="h_ai_biz_model" value="both" <?php checked($biz_model, 'both'); ?>> هر دو پلن</label>
                    <label style="cursor:pointer; color:#25316a;"><input type="radio" name="h_ai_biz_model" value="tier_a" <?php checked($biz_model, 'tier_a'); ?>> پلن A عمده</label>
                    <label style="cursor:pointer; color:#25316a;"><input type="radio" name="h_ai_biz_model" value="tier_b" <?php checked($biz_model, 'tier_b'); ?>> پلن B جزئی</label>
                </div>

                <!-- پلن فروش A -->
                <div id="wrap_tier_a" style="background:#f8fafc; padding:10px; border:1px dashed #c1c9ec; border-radius:6px; margin-bottom:15px;">
                    <h5 style="margin:0 0 10px 0; color:#25316a; font-size:16px;">پلن فروش A (فروش عمده)</h5>
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:50%; padding:5px 10px; border-left:1px solid #c1c9ec;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#b91c1c; font-size:13px;">قیمت دستی عمده (تومان)</label>
                                <input type="number" name="h_ai_fa_price_t1" value="<?php echo esc_attr($fa_price_t1); ?>" placeholder="مثال: 550000" style="width:100%; padding:6px; border:1px solid #c1c9ec; border-radius:4px; font-weight:bold;">
                            </td>
                            <td style="width:50%; padding:5px 10px;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#b91c1c; font-size:13px; direction:rtl; text-align:right;">مبنای این قیمت / Price Basis</label>
                                <div style="display:flex; gap:10px;">
                                    <select name="h_ai_fa_price_basis" style="flex:1; padding:6px; border:1px solid #c1c9ec; border-radius:4px; font-weight:bold;">
                                        <?php foreach($units_fa as $u): ?>
                                            <option value="<?php echo esc_attr($u); ?>" <?php selected($fa_price_basis, $u); ?>><?php echo esc_html($u); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="h_ai_en_price_basis" style="flex:1; padding:6px; border:1px solid #c1c9ec; border-radius:4px; direction:ltr; font-weight:bold;">
                                        <?php foreach($units_en as $u): ?>
                                            <option value="<?php echo esc_attr($u); ?>" <?php selected($en_price_basis, $u); ?>><?php echo esc_html($u); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="width:50%; padding:5px 10px; border-left:1px solid #c1c9ec;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px;">واحد فروش دلخواه (متن آزاد برای کاربر)</label>
                                <input type="text" name="h_ai_fa_sale_unit" value="<?php echo esc_attr($fa_sale_unit); ?>" placeholder="مثال: گونی ۳۰ کیلویی" style="width:100%; padding:6px; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                            <td style="width:50%; padding:5px 10px;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px; direction:ltr; text-align:left;">Sales Unit (Free Text)</label>
                                <input type="text" name="h_ai_en_sale_unit" value="<?php echo esc_attr($en_sale_unit); ?>" placeholder="e.g. 30kg Sack" style="width:100%; padding:6px; direction:ltr; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                        </tr>
                        <tr>
                            <td style="width:50%; padding:5px 10px; border-left:1px solid #c1c9ec;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px;">بسته‌بندی (مثال: ۱ کارتن ۵۰ عددی)</label>
                                <input type="text" name="h_ai_fa_packaging" value="<?php echo esc_attr($fa_packaging); ?>" style="width:100%; padding:6px; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                            <td style="width:50%; padding:5px 10px;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px; direction:ltr; text-align:left;">Packaging</label>
                                <input type="text" name="h_ai_en_packaging" value="<?php echo esc_attr($en_packaging); ?>" placeholder="e.g. 1 Carton (50 pcs)" style="width:100%; padding:6px; direction:ltr; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                        </tr>
                        <tr>
                            <td style="width:50%; padding:5px 10px; border-left:1px solid #c1c9ec;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px;">حداقل سفارش / MOQ (مثال: ۵ کارتن، ۲۰ کیلو)</label>
                                <input type="text" name="h_ai_fa_moq" value="<?php echo esc_attr($fa_moq); ?>" style="width:100%; padding:6px; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                            <td style="width:50%; padding:5px 10px;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px; direction:ltr; text-align:left;">MOQ</label>
                                <input type="text" name="h_ai_en_moq" value="<?php echo esc_attr($en_moq); ?>" placeholder="e.g. 5 Cartons, 20 kg" style="width:100%; padding:6px; direction:ltr; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- پلن فروش B -->
                <div id="wrap_tier_b" style="background:#f8fafc; padding:10px; border:1px dashed #c1c9ec; border-radius:6px;">
                    <h5 style="margin:0 0 10px 0; color:#25316a; font-size:16px;">پلن فروش B (فروش خرد)</h5>
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:50%; padding:5px 10px; border-left:1px solid #c1c9ec;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#b91c1c; font-size:13px;">قیمت دستی خرد (تومان)</label>
                                <input type="number" name="h_ai_fa_price_t2" value="<?php echo esc_attr($fa_price_t2); ?>" placeholder="مثال: 650000" style="width:100%; padding:6px; border:1px solid #c1c9ec; border-radius:4px; font-weight:bold;">
                            </td>
                            <td style="width:50%; padding:5px 10px;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#b91c1c; font-size:13px; direction:rtl; text-align:right;">مبنای این قیمت / Price Basis</label>
                                <div style="display:flex; gap:10px;">
                                    <select name="h_ai_fa_price_basis_t2" style="flex:1; padding:6px; border:1px solid #c1c9ec; border-radius:4px; font-weight:bold;">
                                        <?php foreach($units_fa as $u): ?>
                                            <option value="<?php echo esc_attr($u); ?>" <?php selected($fa_price_basis_t2, $u); ?>><?php echo esc_html($u); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="h_ai_en_price_basis_t2" style="flex:1; padding:6px; border:1px solid #c1c9ec; border-radius:4px; direction:ltr; font-weight:bold;">
                                        <?php foreach($units_en as $u): ?>
                                            <option value="<?php echo esc_attr($u); ?>" <?php selected($en_price_basis_t2, $u); ?>><?php echo esc_html($u); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="width:50%; padding:5px 10px; border-left:1px solid #c1c9ec;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px;">واحد فروش دلخواه (متن آزاد برای کاربر)</label>
                                <input type="text" name="h_ai_fa_sale_unit_t2" value="<?php echo esc_attr($fa_sale_unit_t2); ?>" placeholder="مثال: بسته ۱۰ عددی" style="width:100%; padding:6px; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                            <td style="width:50%; padding:5px 10px;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px; direction:ltr; text-align:left;">Sales Unit (Free Text)</label>
                                <input type="text" name="h_ai_en_sale_unit_t2" value="<?php echo esc_attr($en_sale_unit_t2); ?>" placeholder="e.g. 10-piece Pack" style="width:100%; padding:6px; direction:ltr; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                        </tr>
                        <tr>
                            <td style="width:50%; padding:5px 10px; border-left:1px solid #c1c9ec;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px;">بسته‌بندی (مثال: ۱ بسته، ۱ جین)</label>
                                <input type="text" name="h_ai_fa_packaging_t2" value="<?php echo esc_attr($fa_packaging_t2); ?>" style="width:100%; padding:6px; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                            <td style="width:50%; padding:5px 10px;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px; direction:ltr; text-align:left;">Packaging</label>
                                <input type="text" name="h_ai_en_packaging_t2" value="<?php echo esc_attr($en_packaging_t2); ?>" placeholder="e.g. 1 Pack, 1 Dozen" style="width:100%; padding:6px; direction:ltr; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                        </tr>
                        <tr>
                            <td style="width:50%; padding:5px 10px; border-left:1px solid #c1c9ec;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px;">حداقل سفارش / MOQ (مثال: ۲ بسته، ۵ جین)</label>
                                <input type="text" name="h_ai_fa_moq_t2" value="<?php echo esc_attr($fa_moq_t2); ?>" style="width:100%; padding:6px; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                            <td style="width:50%; padding:5px 10px;">
                                <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a; font-size:13px; direction:ltr; text-align:left;">MOQ</label>
                                <input type="text" name="h_ai_en_moq_t2" value="<?php echo esc_attr($en_moq_t2); ?>" placeholder="e.g. 2 Packs, 5 Dozens" style="width:100%; padding:6px; direction:ltr; border:1px solid #c1c9ec; border-radius:4px;">
                            </td>
                        </tr>
                    </table>
                </div>

            </div>
            <?php endif; ?>

            <div style="background:#ffffff; padding:15px; border:1px solid #c1c9ec; border-right:4px solid #25316a; border-radius:8px; margin-bottom:20px; direction:ltr; text-align:left;">
                <h4 style="margin:0 0 5px 0; color:#25316a;">3. English Technical Specifications</h4>
                <p style="font-size:13px; color:#607bbd; margin:0 0 15px 0;">Add English translations for your product attributes (e.g., Material: Plastic).</p>
                <div id="h_ai_en_specs_container"></div>
                <button type="button" id="h_ai_add_en_spec" style="background:#25316a; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:bold; margin-top:10px;">+ Add English Spec</button>
                <input type="hidden" name="h_ai_en_specs" id="h_ai_en_specs_input" value="<?php echo esc_attr( $en_specs ); ?>">
            </div>

            <div style="background:#ffffff; padding:15px; border:1px solid #c1c9ec; border-right:4px solid #607bbd; border-radius:8px; margin-bottom:20px;">
                <h4 style="margin:0 0 15px 0; padding-bottom:5px; border-bottom:1px solid #c1c9ec; color:#25316a;">۴. مدیریت رسانه‌ها (Audio & Video)</h4>
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:50%; padding:10px; border-left:1px solid #c1c9ec;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">عنوان ویدیو</label>
                            <input type="text" name="h_ai_fa_vid_title" value="<?php echo esc_attr($fa_vid_title); ?>" placeholder="مثال: ویدیوی معرفی" style="width:100%; padding:8px; border:1px solid #c1c9ec; border-radius:4px;">
                        </td>
                        <td style="width:50%; padding:10px;">
                            <div style="display:flex; gap:10px; direction:ltr; text-align:left;">
                                <div style="flex:2;">
                                    <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">Video URL (.mp4)</label>
                                    <input type="text" name="h_ai_vid_url" value="<?php echo esc_attr($vid_url); ?>" placeholder="https://..." style="width:100%; padding:8px; border:1px solid #c1c9ec; border-radius:4px;">
                                </div>
                                <div style="flex:1;">
                                    <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">Title</label>
                                    <input type="text" name="h_ai_en_vid_title" value="<?php echo esc_attr($en_vid_title); ?>" placeholder="e.g. Intro Video" style="width:100%; padding:8px; border:1px solid #c1c9ec; border-radius:4px;">
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px; border-left:1px solid #c1c9ec;">
                            <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">عنوان پادکست</label>
                            <input type="text" name="h_ai_fa_pod_title" value="<?php echo esc_attr($fa_pod_title); ?>" placeholder="مثال: پادکست بررسی" style="width:100%; padding:8px; border:1px solid #c1c9ec; border-radius:4px;">
                        </td>
                        <td style="padding:10px;">
                            <div style="display:flex; gap:10px; direction:ltr; text-align:left;">
                                <div style="flex:2;">
                                    <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">Podcast URL (.mp3)</label>
                                    <input type="text" name="h_ai_pod_url" value="<?php echo esc_attr($pod_url); ?>" placeholder="https://..." style="width:100%; padding:8px; border:1px solid #c1c9ec; border-radius:4px;">
                                </div>
                                <div style="flex:1;">
                                    <label style="display:block; font-weight:bold; margin-bottom:5px; color:#25316a;">Title</label>
                                    <input type="text" name="h_ai_en_pod_title" value="<?php echo esc_attr($en_pod_title); ?>" placeholder="e.g. Audio Review" style="width:100%; padding:8px; border:1px solid #c1c9ec; border-radius:4px;">
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="display:flex; gap:20px;">
                <div style="flex:1; background:#ffffff; padding:15px; border:1px solid #c1c9ec; border-radius:8px;">
                    <h4 style="margin:0 0 10px 0; color:#25316a;">۵. پرسش و پاسخ فارسی</h4>
                    <div id="h_ai_fa_faq_container"></div>
                    <button type="button" id="h_ai_add_fa_faq" style="background:#25316a; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:bold; margin-top:8px;">+ افزودن پرسش فارسی</button>
                    <input type="hidden" name="h_ai_faqs" id="h_ai_faqs_input" value="<?php echo esc_attr( $fa_faqs ); ?>">
                    
                    <h4 style="margin:20px 0 10px 0; color:#25316a;">پرسش‌های پیشنهادی فارسی (هر سوال در یک خط)</h4>
                    <textarea name="h_ai_prompts" style="width:100%; height:70px; padding:8px; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($fa_prompts); ?></textarea>
                </div>
                <div style="flex:1; background:#ffffff; padding:15px; border:1px solid #c1c9ec; border-radius:8px; direction:ltr; text-align:left;">
                    <h4 style="margin:0 0 10px 0; color:#25316a;">5. English FAQs</h4>
                    <div id="h_ai_en_faq_container"></div>
                    <button type="button" id="h_ai_add_en_faq" style="background:#25316a; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:bold; margin-top:8px;">+ Add FAQ</button>
                    <input type="hidden" name="h_ai_en_faqs" id="h_ai_en_faqs_input" value="<?php echo esc_attr( $en_faqs ); ?>">
                    
                    <h4 style="margin:20px 0 10px 0; color:#25316a;">Suggested Prompts (One per line)</h4>
                    <textarea name="h_ai_en_prompts" style="width:100%; height:70px; padding:8px; border:1px solid #c1c9ec; border-radius:4px;"><?php echo esc_textarea($en_prompts); ?></textarea>
                </div>
            </div>

            <div style="margin-top:20px; padding: 15px; background: #ffffff; border: 1px solid #c1c9ec; border-right: 4px solid #25316a; border-radius: 4px; color: #25316a; font-size: 14px; font-weight: bold; display: flex; align-items: center;">
                راهنما: افزودن پسوند <code style="margin: 0 5px; padding: 2px 6px; background: #f8fafc; border: 1px solid #c1c9ec; border-radius: 4px; color: #607bbd;">.md</code> به انتهای آدرس صفحه (در زیرپوشه‌های /fa/ و /en/) جهت مشاهده خروجی ماشین
            </div>

        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var bizRadios = document.querySelectorAll('input[name="h_ai_biz_model"]');
            var wrapA = document.getElementById('wrap_tier_a');
            var wrapB = document.getElementById('wrap_tier_b');
            
            function updateBizModel() {
                var checkedRadio = document.querySelector('input[name="h_ai_biz_model"]:checked');
                if(!checkedRadio) return;
                var val = checkedRadio.value;
                if(wrapA) wrapA.style.display = (val === 'both' || val === 'tier_a') ? 'block' : 'none';
                if(wrapB) wrapB.style.display = (val === 'both' || val === 'tier_b') ? 'block' : 'none';
            }
            
            bizRadios.forEach(function(r) { r.addEventListener('change', updateBizModel); });
            updateBizModel();

            function initFaqRepeater(containerId, inputId, btnId, isEnglish) {
                var container = document.getElementById(containerId);
                var input = document.getElementById(inputId);
                var btn = document.getElementById(btnId);
                if(!container || !input || !btn) return;

                var data = [];
                try { data = JSON.parse(input.value || '[]'); } catch (e) { data = []; }

                function draw() {
                    container.innerHTML = '';
                    data.forEach(function (item, i) {
                        var box = document.createElement('div');
                        box.style.cssText = 'background:#f8fafc; padding:10px; margin-top:8px; border:1px solid #c1c9ec; border-radius:6px; display:flex; flex-direction:column; gap:8px;';
                        
                        var q = document.createElement('input');
                        q.type = 'text'; q.placeholder = isEnglish ? 'Question...' : 'پرسش...'; q.value = item.q || '';
                        q.style.cssText = isEnglish ? 'direction:ltr; text-align:left;' : 'direction:rtl;';
                        q.style.padding = '8px'; q.style.border = '1px solid #c1c9ec'; q.style.borderRadius = '4px'; q.style.fontWeight = 'bold';
                        q.addEventListener('input', function () { data[i].q = q.value; input.value = JSON.stringify(data); });

                        var a = document.createElement('textarea');
                        a.placeholder = isEnglish ? 'Answer...' : 'پاسخ...'; a.value = item.a || '';
                        a.style.cssText = isEnglish ? 'direction:ltr; text-align:left; height:60px;' : 'direction:rtl; height:60px;';
                        a.style.padding = '8px'; a.style.border = '1px solid #c1c9ec'; a.style.borderRadius = '4px';
                        a.addEventListener('input', function () { data[i].a = a.value; input.value = JSON.stringify(data); });

                        var del = document.createElement('button');
                        del.type = 'button'; del.textContent = isEnglish ? 'Remove' : 'حذف';
                        del.style.cssText = 'align-self:flex-end; color:#25316a; background:none; border:none; cursor:pointer; font-weight:bold;';
                        del.addEventListener('click', function () { data.splice(i, 1); draw(); });

                        box.append(q, a, del);
                        container.appendChild(box);
                    });
                    input.value = JSON.stringify(data);
                }
                btn.addEventListener('click', function () { data.push({ q: '', a: '' }); draw(); });
                draw();
            }

            function initSpecRepeater() {
                var container = document.getElementById('h_ai_en_specs_container');
                var input = document.getElementById('h_ai_en_specs_input');
                var btn = document.getElementById('h_ai_add_en_spec');
                if(!container || !input || !btn) return;

                var data = [];
                try { data = JSON.parse(input.value || '[]'); } catch (e) { data = []; }

                function draw() {
                    container.innerHTML = '';
                    data.forEach(function (item, i) {
                        var box = document.createElement('div');
                        box.style.cssText = 'background:#f8fafc; padding:10px; margin-top:8px; border:1px solid #c1c9ec; border-radius:6px; display:flex; gap:10px; align-items:center;';
                        
                        var k = document.createElement('input');
                        k.type = 'text'; k.placeholder = 'Feature (e.g. Material)'; k.value = item.k || '';
                        k.style.cssText = 'flex:1; padding:8px; border:1px solid #c1c9ec; border-radius:4px;';
                        k.addEventListener('input', function() { data[i].k = k.value; input.value = JSON.stringify(data); });

                        var v = document.createElement('input');
                        v.type = 'text'; v.placeholder = 'Value (e.g. Plastic)'; v.value = item.v || '';
                        v.style.cssText = 'flex:2; padding:8px; border:1px solid #c1c9ec; border-radius:4px;';
                        v.addEventListener('input', function() { data[i].v = v.value; input.value = JSON.stringify(data); });

                        var del = document.createElement('button');
                        del.type = 'button'; del.textContent = '✖';
                        del.style.cssText = 'color:#25316a; background:none; border:none; cursor:pointer; font-weight:bold; font-size:16px; padding:0 5px;';
                        del.addEventListener('click', function() { data.splice(i, 1); draw(); });

                        box.append(k, v, del);
                        container.appendChild(box);
                    });
                    input.value = JSON.stringify(data);
                }
                btn.addEventListener('click', function () { data.push({ k: '', v: '' }); draw(); });
                draw();
            }

            initFaqRepeater('h_ai_fa_faq_container', 'h_ai_faqs_input', 'h_ai_add_fa_faq', false);
            initFaqRepeater('h_ai_en_faq_container', 'h_ai_en_faqs_input', 'h_ai_add_en_faq', true);
            initSpecRepeater();
        });
        </script>
        <?php
    }

    public static function post_ui( $post ): void {
        wp_nonce_field( 'h_ai_n', 'h_ai_nonce' );
        self::render_ui_fields( $post->ID, 'post' );
    }

    public static function tax_edit_ui( $term ): void {
        wp_nonce_field( 'h_ai_n', 'h_ai_nonce' );
        ?>
        <tr class="form-field">
            <td colspan="2" style="padding: 20px 0;">
                <h3 style="font-size: 18px; color: #25316a; border-bottom: 2px solid #c1c9ec; padding-bottom: 10px; margin-bottom: 15px;">تنظیمات پیشرفته AEO * GEO</h3>
                <?php self::render_ui_fields( $term->term_id, 'term' ); ?>
            </td>
        </tr>
        <?php
    }

    public static function save_post_meta( $id ): void {
        if ( ! isset( $_POST['h_ai_nonce'] ) || ! wp_verify_nonce( $_POST['h_ai_nonce'], 'h_ai_n' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $id ) ) return;
        self::process_and_save( $id, 'post' );
    }

    public static function save_tax_meta( $term_id ): void {
        if ( ! isset( $_POST['h_ai_nonce'] ) || ! wp_verify_nonce( $_POST['h_ai_nonce'], 'h_ai_n' ) ) return;
        if ( ! current_user_can( 'manage_categories' ) ) return;
        self::process_and_save( $term_id, 'term' );
    }

    private static function process_and_save( int $object_id, string $type ): void {
        $meta_data = [
            '_h_ai_entities'              => sanitize_text_field( $_POST['h_ai_entities'] ?? '' ),
            '_h_ai_fa_aliases'            => sanitize_text_field( $_POST['h_ai_fa_aliases'] ?? '' ),
            '_h_ai_text'                  => sanitize_textarea_field( $_POST['h_ai_text'] ?? '' ),
            '_h_ai_usecases'              => sanitize_textarea_field( $_POST['h_ai_usecases'] ?? '' ),
            '_h_ai_audience'              => sanitize_textarea_field( $_POST['h_ai_audience'] ?? '' ),
            '_h_ai_comparison'            => sanitize_textarea_field( $_POST['h_ai_comparison'] ?? '' ),
            '_h_ai_prompts'               => sanitize_textarea_field( $_POST['h_ai_prompts'] ?? '' ),
            '_h_ai_fa_embedding_summary'  => sanitize_textarea_field( $_POST['h_ai_fa_embedding_summary'] ?? '' ),

            '_h_ai_en_entity'             => sanitize_text_field( $_POST['h_ai_en_entity'] ?? '' ),
            '_h_ai_en_aliases'            => sanitize_text_field( $_POST['h_ai_en_aliases'] ?? '' ),
            '_h_ai_en_synonyms'           => sanitize_text_field( $_POST['h_ai_en_synonyms'] ?? '' ),
            '_h_ai_en_context'            => sanitize_textarea_field( $_POST['h_ai_en_context'] ?? '' ),
            '_h_ai_en_usecases'           => sanitize_textarea_field( $_POST['h_ai_en_usecases'] ?? '' ),
            '_h_ai_en_audience'           => sanitize_textarea_field( $_POST['h_ai_en_audience'] ?? '' ),
            '_h_ai_en_comparison'         => sanitize_textarea_field( $_POST['h_ai_en_comparison'] ?? '' ),
            '_h_ai_en_prompts'            => sanitize_textarea_field( $_POST['h_ai_en_prompts'] ?? '' ),
            '_h_ai_en_embedding_summary'  => sanitize_textarea_field( $_POST['h_ai_en_embedding_summary'] ?? '' ),
            
            '_h_ai_biz_model'             => sanitize_text_field( $_POST['h_ai_biz_model'] ?? 'both' ),

            '_h_ai_fa_price_t1'           => sanitize_text_field( $_POST['h_ai_fa_price_t1'] ?? '' ),
            '_h_ai_fa_sale_unit'          => sanitize_text_field( $_POST['h_ai_fa_sale_unit'] ?? '' ),
            '_h_ai_en_sale_unit'          => sanitize_text_field( $_POST['h_ai_en_sale_unit'] ?? '' ),
            '_h_ai_fa_price_basis'        => sanitize_text_field( $_POST['h_ai_fa_price_basis'] ?? 'کیلوگرم' ),
            '_h_ai_en_price_basis'        => sanitize_text_field( $_POST['h_ai_en_price_basis'] ?? 'kg' ),
            '_h_ai_fa_packaging'          => sanitize_text_field( $_POST['h_ai_fa_packaging'] ?? '' ),
            '_h_ai_en_packaging'          => sanitize_text_field( $_POST['h_ai_en_packaging'] ?? '' ),
            '_h_ai_fa_moq'                => sanitize_text_field( $_POST['h_ai_fa_moq'] ?? '' ),
            '_h_ai_en_moq'                => sanitize_text_field( $_POST['h_ai_en_moq'] ?? '' ),

            '_h_ai_fa_price_t2'           => sanitize_text_field( $_POST['h_ai_fa_price_t2'] ?? '' ),
            '_h_ai_fa_sale_unit_t2'       => sanitize_text_field( $_POST['h_ai_fa_sale_unit_t2'] ?? '' ),
            '_h_ai_en_sale_unit_t2'       => sanitize_text_field( $_POST['h_ai_en_sale_unit_t2'] ?? '' ),
            '_h_ai_fa_price_basis_t2'     => sanitize_text_field( $_POST['h_ai_fa_price_basis_t2'] ?? 'بسته' ),
            '_h_ai_en_price_basis_t2'     => sanitize_text_field( $_POST['h_ai_en_price_basis_t2'] ?? 'Pack' ),
            '_h_ai_fa_packaging_t2'       => sanitize_text_field( $_POST['h_ai_fa_packaging_t2'] ?? '' ),
            '_h_ai_en_packaging_t2'       => sanitize_text_field( $_POST['h_ai_en_packaging_t2'] ?? '' ),
            '_h_ai_fa_moq_t2'             => sanitize_text_field( $_POST['h_ai_fa_moq_t2'] ?? '' ),
            '_h_ai_en_moq_t2'             => sanitize_text_field( $_POST['h_ai_en_moq_t2'] ?? '' ),

            '_h_ai_vid_url'               => esc_url_raw( $_POST['h_ai_vid_url'] ?? '' ),
            '_h_ai_en_vid_title'          => sanitize_text_field( $_POST['h_ai_en_vid_title'] ?? '' ),
            '_h_ai_fa_vid_title'          => sanitize_text_field( $_POST['h_ai_fa_vid_title'] ?? '' ),
            '_h_ai_pod_url'               => esc_url_raw( $_POST['h_ai_pod_url'] ?? '' ),
            '_h_ai_en_pod_title'          => sanitize_text_field( $_POST['h_ai_en_pod_title'] ?? '' ),
            '_h_ai_fa_pod_title'          => sanitize_text_field( $_POST['h_ai_fa_pod_title'] ?? '' ),
        ];

        delete_post_meta( $object_id, '_h_ai_pillars' );
        delete_post_meta( $object_id, '_h_ai_clusters' );

        $raw_faqs = json_decode( stripslashes( $_POST['h_ai_faqs'] ?? '[]' ), true );
        $clean_faqs = [];
        if ( is_array( $raw_faqs ) ) {
            foreach ( $raw_faqs as $f ) {
                if ( ! empty( $f['q'] ) && ! empty( $f['a'] ) ) {
                    $clean_faqs[] = [ 'q' => sanitize_text_field( $f['q'] ), 'a' => sanitize_textarea_field( $f['a'] ) ];
                }
            }
        }
        $meta_data['_h_ai_faqs'] = wp_json_encode( $clean_faqs, JSON_UNESCAPED_UNICODE );

        $raw_en_faqs = json_decode( stripslashes( $_POST['h_ai_en_faqs'] ?? '[]' ), true );
        $clean_en_faqs = [];
        if ( is_array( $raw_en_faqs ) ) {
            foreach ( $raw_en_faqs as $f ) {
                if ( ! empty( $f['q'] ) && ! empty( $f['a'] ) ) {
                    $clean_en_faqs[] = [ 'q' => sanitize_text_field( $f['q'] ), 'a' => sanitize_textarea_field( $f['a'] ) ];
                }
            }
        }
        $meta_data['_h_ai_en_faqs'] = wp_json_encode( $clean_en_faqs, JSON_UNESCAPED_UNICODE );

        $raw_en_specs = json_decode( stripslashes( $_POST['h_ai_en_specs'] ?? '[]' ), true );
        $clean_en_specs = [];
        if ( is_array( $raw_en_specs ) ) {
            foreach ( $raw_en_specs as $s ) {
                if ( ! empty( $s['k'] ) && ! empty( $s['v'] ) ) {
                    $clean_en_specs[] = [ 'k' => sanitize_text_field( $s['k'] ), 'v' => sanitize_text_field( $s['v'] ) ];
                }
            }
        }
        $meta_data['_h_ai_en_specs'] = wp_json_encode( $clean_en_specs, JSON_UNESCAPED_UNICODE );

        foreach ( $meta_data as $key => $value ) {
            if ( $type === 'post' ) {
                update_post_meta( $object_id, $key, $value );
            } else {
                update_term_meta( $object_id, $key, $value );
            }
        }

        // --- پاک کردن کش فایل‌های تفکیک شده دوزبانه (.md) ---
        if ( $type === 'post' ) {
            delete_transient( "hodima_md_fa_post_{$object_id}" );
            delete_transient( "hodima_md_en_post_{$object_id}" );
        } else {
            delete_transient( "hodima_md_fa_term_{$object_id}" );
            delete_transient( "hodima_md_en_term_{$object_id}" );
        }
        
        // --- پاک کردن کش سراسری llms.txt برای هر دو زبان ---
        delete_transient( 'hodima_llms_txt_cache_fa_20_siloed' );
        delete_transient( 'hodima_llms_txt_cache_en_20_siloed' );
        delete_transient( 'hodima_llms_txt_cache_fa_500_siloed' );
        delete_transient( 'hodima_llms_txt_cache_en_500_siloed' );
    }
}