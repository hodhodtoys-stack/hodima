/**
 * ESLint هدیما (اجرا: bash bin/lint.sh یا npm run lint:js)
 *
 * جاوااسکریپت خالص مرورگر (بدون jQuery و بدون ابزار ساخت). پایه: قواعد
 * پیشنهادی ESLint + نوسازی (const/let به‌جای var، arrow، template literal،
 * optional chaining). خطاهای قدیمی در tools/quality/baseline/eslint.json.
 */
import js from '@eslint/js';
import globals from 'globals';

export default [
	{
		ignores: [ '**/vendor/**', '**/node_modules/**', 'tools/wp-harness/**', 'release/**', 'dist/**' ],
	},
	js.configs.recommended,
	{
		files: [ 'hodima/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2024,
			sourceType: 'script',
			globals: {
				...globals.browser,
				// اسکریپت‌های پیشخوان وردپرس
				wp: 'readonly',
			},
		},
		rules: {
			// نوسازی
			'no-var': 'error',
			'prefer-const': 'error',
			'prefer-arrow-callback': 'error',
			'prefer-template': 'error',
			'object-shorthand': 'error',
			'prefer-object-has-own': 'error',
			// خطاهای رایج
			eqeqeq: [ 'error', 'smart' ],
			'no-alert': 'error',
			'no-implicit-globals': 'error',
			'no-unused-vars': [ 'error', { args: 'after-used', caughtErrors: 'none' } ],
			'no-restricted-globals': [ 'error', { name: 'jQuery', message: 'قالب بدون jQuery است (JavaScript خالص).' }, { name: '$', message: 'قالب بدون jQuery است (JavaScript خالص).' } ],
		},
	},
];
