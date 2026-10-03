<?php
/**
 * خوشه موضوعی — نوع گره و ارجاع به گره
 * Path: core/topiccluster/includes/ref.php
 *
 * شناسه نوشته‌ها و ترم‌ها دو دنباله مستقل‌اند («۴۲» می‌تواند هم نوشته باشد
 * هم دسته). نسخه‌های قبلی همه‌جا یک عدد خام و یک رشته 'post'/'term' جدا
 * جابه‌جا می‌کردند و چند باگ از همین آمد (مثلا AEO شناسه دسته را شناسه
 * نوشته می‌خواند). از نسخه ۴ هر گره یک Ref است: نوع + شناسه، با هم.
 */

declare(strict_types=1);

namespace Hodima\TopicCluster;

defined( 'ABSPATH' ) || exit;

enum Kind: string {

	case Post = 'post';
	case Term = 'term';

	/** نوع متای وردپرس (get_metadata) — همان مقدار. */
	public function meta_type(): string {
		return $this->value;
	}

	public static function from_context( string $context ): self {
		return 'term' === $context ? self::Term : self::Post;
	}
}

final readonly class Ref {

	public function __construct(
		public Kind $kind,
		public int $id,
	) {}

	public static function post( int $id ): self {
		return new self( Kind::Post, $id );
	}

	public static function term( int $id ): self {
		return new self( Kind::Term, $id );
	}

	/** «post:12» / «term:5» → Ref، یا null اگر نامعتبر. */
	public static function parse( string $key ): ?self {
		if ( ! preg_match( '/^(post|term):(\d+)$/', trim( $key ), $m ) || (int) $m[2] <= 0 ) {
			return null;
		}
		return new self( Kind::from( $m[1] ), (int) $m[2] );
	}

	public function key(): string {
		return $this->kind->value . ':' . $this->id;
	}

	public function is( self $other ): bool {
		return $this->kind === $other->kind && $this->id === $other->id;
	}

	public function is_post(): bool {
		return Kind::Post === $this->kind;
	}

	public function is_term(): bool {
		return Kind::Term === $this->kind;
	}
}
