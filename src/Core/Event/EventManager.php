<?php

namespace Quinrel\WordPress\Core\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventManager {

	public function addAction( string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1 ): void {
		add_action( $hook, $callback, $priority, $acceptedArgs );
	}

	public function removeAction( string $hook, callable|string|array $callback, int $priority = 10 ): bool {
		return remove_action( $hook, $callback, $priority );
	}

	public function addFilter( string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1 ): void {
		add_filter( $hook, $callback, $priority, $acceptedArgs );
	}

	public function doAction( string $hook, ...$args ): void {
		$suffix = $this->hookSuffix( $hook );
		if ( '' === $suffix ) {
			return;
		}

		do_action( 'quinrel_' . $suffix, ...$args );
	}

	public function applyFilters( string $hook, $value, ...$args ) {
		$suffix = $this->hookSuffix( $hook );
		if ( '' === $suffix ) {
			return $value;
		}

		return apply_filters( 'quinrel_' . $suffix, $value, ...$args );
	}

	/**
	 * Suffix for a Quinrel hook. A leading quinrel_ is removed once so the name is not doubled.
	 */
	private function hookSuffix( string $hook ): string {
		$hook = strtolower( $hook );
		if ( str_starts_with( $hook, 'quinrel_' ) ) {
			$hook = substr( $hook, strlen( 'quinrel_' ) );
		}

		if ( '' === $hook || 1 !== preg_match( '/^[a-z0-9_]+$/', $hook ) ) {
			return '';
		}

		return $hook;
	}
}
