<?php

namespace IAmJulianAcosta\JsonApi\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\Application;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest() {
        $this->assertTrue(true);
    }
	
	/**
	 * Define environment setup.
	 *
	 * @param  Application  $app
	 *
	 * @return void
	 */
	protected function getEnvironmentSetUp($app) {
		$app['router']->get('hello', ['as' => 'hi', 'uses' => function () {
			return 'hello world';
		}]);
	}
	
	public function testGetRoutes() {
		$crawler = $this->call('GET', 'hello');
		$this->assertEquals('hello world', $crawler->getContent());
	}
}
