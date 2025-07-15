<?php

namespace MonedaPay\PaymentGateway\Tests;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

class ExampleTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_example(): void {
		$this->assertTrue( true );
	}
}
