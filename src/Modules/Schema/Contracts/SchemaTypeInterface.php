<?php

namespace Quinrel\WordPress\Modules\Schema\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for modular schema generators.
 */
interface SchemaTypeInterface {

	/**
	 * Unique identifier for the schema type (e.g. 'article', 'podcast', 'course').
	 */
	public function getId(): string;

	/**
	 * Determines if this schema is applicable for the given post.
	 */
	public function isApplicable( ?int $postId = null ): bool;

	/**
	 * Generates the Schema.org JSON-LD structured data payload.
	 *
	 * @return array<string, mixed>|list<array<string, mixed>>
	 */
	public function generate( ?int $postId = null ): array;
}
