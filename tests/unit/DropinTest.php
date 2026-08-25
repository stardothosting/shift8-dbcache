<?php

use PHPUnit\Framework\TestCase;

class DropinTest extends TestCase {
	public function test_sync_installs_and_removes_own_dropin_based_on_setting() {
		global $shift8_dbcache_test_options;
		$path = Shift8_DBCache_Dropin::get_dropin_path();

		if ( ! is_dir( dirname( $path ) ) ) {
			mkdir( dirname( $path ), 0777, true );
		}

		if ( file_exists( $path ) ) {
			unlink( $path );
		}

		$settings = Shift8_DBCache_Settings::get_default_settings();
		$settings['redis_enabled'] = '1';
		$shift8_dbcache_test_options = array(
			'shift8_dbcache_settings' => $settings,
		);

		$this->assertTrue( Shift8_DBCache_Dropin::sync() );
		$this->assertTrue( file_exists( $path ) );
		$this->assertTrue( Shift8_DBCache_Dropin::is_ours() );

		$settings['redis_enabled'] = '0';
		$shift8_dbcache_test_options['shift8_dbcache_settings'] = $settings;

		$this->assertTrue( Shift8_DBCache_Dropin::sync() );
		$this->assertFalse( file_exists( $path ) );
	}
}