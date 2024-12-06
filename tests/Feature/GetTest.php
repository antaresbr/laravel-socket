<?php
namespace Antares\Socket\Tests\Feature;

use Antares\Socket\Socket;
use Antares\Socket\Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class GetTest extends TestCase
{
    use WithoutMiddleware;

    /** @test */
    public function get_with_not_found_id()
    {
        $response = $this->get(config('socket.route.prefix.api') . '/get/dummy_id');
        $response->assertStatus(404);
    }

    /** @test */
    public function get_successful()
    {
        $socket = Socket::make([
            'id' => 'sub:maked_socket',
            'status' => Socket::STATUS_NEW,
            'title' => 'Maked socket',
            'progress' => [
                'enabled' => true,
                'maximum' => 10,
            ],
        ]);

        $response = $this->get(config('socket.route.prefix.api') . '/get/' . $socket->get('id'));
        $response->assertStatus(200);

        $json = json_encode($response->json());
        $this->assertEquals(json_encode($socket->data()), $json);
    }
}
