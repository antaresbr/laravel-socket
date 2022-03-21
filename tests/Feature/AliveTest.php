<?php
namespace Antares\Socket\Tests\Feature;

use Antares\Socket\Tests\TestCase;

class AliveTest extends TestCase
{
    /** @test */
    public function get_alive()
    {
        $response = $this->get(config('socket.route.prefix.api') . '/alive');
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertArrayHasKey('package', $json);
        $this->assertArrayHasKey('env', $json);
        $this->assertArrayHasKey('serverDateTime', $json);
    }
}
