<?php
/**
 * Hodima Stories — استوری‌های ویدیویی: پنل پیشخوان، شورت‌کد [hodima-Stories]،
 * پخش‌کننده و آمار بازدید/اشتراک.
 *
 * این فایل فقط بارگذار است (ماژول «استوری» Hodima Media همین را لود می‌کند)؛
 * هر کلاس در فایل خودش زیر includes/.
 *
 * @package Hodima_Media
 */

declare(strict_types=1);

namespace Hodima\Stories;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-story-setup.php';
require_once __DIR__ . '/includes/class-story-analytics.php';
require_once __DIR__ . '/includes/class-core.php';
require_once __DIR__ . '/includes/class-helpers.php';
require_once __DIR__ . '/includes/class-analytics-api.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-frontend.php';
require_once __DIR__ . '/hodima-StoryPlayer/hodima-StoryPlayer.php';

Core::init();
