<?php
namespace Antares\Socket\Tests;

use Antares\Socket\Providers\SocketServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            SocketServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('SOCKET_DATA')) {
            define('SOCKET_DATA', ai_socket_path('tests/data'));
        }
    }
}
