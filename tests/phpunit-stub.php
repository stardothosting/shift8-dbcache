<?php

namespace PHPUnit\Framework;

abstract class TestCase {
	protected function assertCount( $expectedCount, $haystack, $message = '' ) {}
	protected function assertArrayHasKey( $key, $array, $message = '' ) {}
	protected function assertSame( $expected, $actual, $message = '' ) {}
	protected function assertTrue( $condition, $message = '' ) {}
	protected function assertFalse( $condition, $message = '' ) {}
	protected function assertNull( $actual, $message = '' ) {}
	protected function assertContains( $needle, $haystack, $message = '' ) {}
}