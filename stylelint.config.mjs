/**
 * Stylelint هدیما (اجرا: bash bin/lint.sh یا npm run lint:css)
 *
 * پایه: stylelint-config-standard، با تمرکز بر خطا و نوسازی:
 *   - نگارش مدرن رنگ و media query (rgb(0 0 0 / 0.1)، width <= 768px)
 *   - !important (هدف مرحله ۶ نقشه راه: کم کردن ۷۳۲ مورد)
 *   - خصوصیات منطقی (margin-inline-start به‌جای margin-left؛ سایت راست‌به‌چپ است)
 *   - انتخابگر تکراری و ترتیب ویژگی (specificity) که باعث بازنویسی ناخواسته می‌شود
 * قواعد صرفا ظاهری (خط خالی، طول هگز، الگوی نام کلاس BEM) خاموش‌اند.
 *
 * خطاهای قدیمی در tools/quality/baseline/stylelint.json ثبت‌اند؛ فقط بیشتر
 * شدن خطا (در یک فایل و یک قاعده) رد می‌شود.
 */
export default {
	extends: [ 'stylelint-config-standard' ],
	plugins: [ 'stylelint-use-logical' ],
	ignoreFiles: [ '**/vendor/**', '**/node_modules/**' ],
	rules: {
		// ظاهری
		'selector-class-pattern': null,
		'selector-id-pattern': null,
		'custom-property-pattern': null,
		'keyframes-name-pattern': null,
		'rule-empty-line-before': null,
		'at-rule-empty-line-before': null,
		'comment-empty-line-before': null,
		'custom-property-empty-line-before': null,
		'declaration-empty-line-before': null,
		'color-hex-length': null,
		'font-family-name-quotes': null,
		'length-zero-no-unit': null,
		'value-keyword-case': null,
		'declaration-block-single-line-max-declarations': null,

		/*
		 * رنگ نیمه‌شفاف برند قرارداد پروژه است: rgba(var(--hodima-primary-rgb, 37, 49, 106), 0.1)
		 * (CLAUDE.md؛ افزونه‌ها هم همین را دارند). متغیر «R, G, B» ویرگول دارد و با نگارش
		 * فاصله‌ای rgb(… / a) نمی‌خواند؛ color-mix(var) هم در مرورگرهای هدف قدیمی (Chrome 109
		 * ویندوز ۷) پایین آورده نمی‌شود (HODIMA-AUDIT.md ۶۳). رنگ ثابت: rgb(0 0 0 / 0.1).
		 * آلفا عدد (0.1) مثل بقیه کد.
		 */
		'color-function-notation': [ 'modern', { ignore: [ 'with-var-inside' ] } ],
		'color-function-alias-notation': null,
		'alpha-value-notation': 'number',

		// نوسازی و درستی
		'declaration-no-important': true,
		'csstools/use-logical': [ 'always', {
			// top/bottom در نوشتار افقی همان block-start/end است؛ فقط چپ/راست مهم است
			except: [ 'top', 'bottom', 'margin-top', 'margin-bottom', 'padding-top', 'padding-bottom',
				'border-top', 'border-bottom', 'border-top-width', 'border-bottom-width',
				'border-top-color', 'border-bottom-color', 'border-top-style', 'border-bottom-style',
				'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height' ],
		} ],
	},
};
