<?php
/**
 * db.php drop-in installation helpers.
 *
 * @package Shift8\DBCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shift8_DBCache_Dropin {
	const MARKER = 'SHIFT8_DBCACHE_DROPIN';

	public static function sync() {
		if ( self::should_install() ) {
			return self::install();
		}

		return self::remove();
	}

	public static function get_status() {
		$path = self::get_dropin_path();
		$exists = file_exists( $path );
		$is_ours = self::is_ours();
		$directory = dirname( $path );

		return array(
			'path' => $path,
			'exists' => $exists,
			'is_ours' => $is_ours,
			'conflict' => $exists && ! $is_ours,
			'writable' => ( $exists && is_writable( $path ) ) || ( is_dir( $directory ) && is_writable( $directory ) ),
			'should_install' => self::should_install(),
		);
	}

	public static function install() {
		$status = self::get_status();

		if ( $status['conflict'] ) {
			return false;
		}

		$source = self::get_source_path();
		if ( ! file_exists( $source ) ) {
			return false;
		}

		return false !== file_put_contents( self::get_dropin_path(), file_get_contents( $source ) );
	}

	public static function remove() {
		if ( ! self::is_ours() ) {
			return true;
		}

		return unlink( self::get_dropin_path() );
	}

	public static function is_ours() {
		$path = self::get_dropin_path();

		if ( ! file_exists( $path ) ) {
			return false;
		}

		$content = file_get_contents( $path );

		return is_string( $content ) && false !== strpos( $content, self::MARKER );
	}

	public static function should_install() {
		return Shift8_DBCache_Settings::is_active_cache_enabled();
	}

	public static function get_dropin_path() {
		return rtrim( WP_CONTENT_DIR, '/\\' ) . '/db.php';
	}

	private static function get_source_path() {
		return SHIFT8_DBCACHE_PATH . 'dropins/db.php';
	}
}