<?php
namespace Antares\Socket\Tests\Feature;

use Antares\Socket\Socket;
use Antares\Socket\Tests\TestCase;
use Carbon\Carbon;

class SocketTest extends TestCase
{
    /** @test */
    public function create_socket_without_prefix()
    {
        $socket = new Socket();
        $this->assertInstanceOf(Socket::class, $socket);
        $this->assertStringNotContainsString(':', $socket->get('id'));
    }

    /** @test */
    public function create_socket_with_prefix()
    {
        $socket = new Socket('prefix');
        $this->assertInstanceOf(Socket::class, $socket);
        $this->assertStringStartsWith('prefix:' . Carbon::now()->format('Y-m-d'), $socket->get('id'));
    }

    /** @test */
    public function socket_data_manipulation()
    {
        $socket = new Socket();

        $socket->set('title', 'New socket title');
        $this->assertEquals('New socket title', $socket->get('title'));

        $this->assertFalse($socket->get('progress.enabled'));
        $socket->set('progress.enabled', true);
        $this->assertTrue($socket->get('progress.enabled'));

        $socket->set('result.files', ['file-a', 'file-b', 'file-c']);
        $this->assertIsArray($socket->get('result.files'));
        $this->assertCount(3, $socket->get('result.files'));
    }

    protected function makedSocket()
    {
        return Socket::make([
            'id' => 'sub:maked_socket',
            'status' => 'new',
            'title' => 'Maked socket',
            'progress' => [
                'enabled' => true,
                'maximum' => 10,
            ],
        ]);
    }

    /** @test */
    public function make_socket()
    {
        $socket = $this->makedSocket();

        $this->assertInstanceOf(Socket::class, $socket);
        $this->assertStringStartsWith('sub:', $socket->get('id'));
        $this->assertEquals('new', $socket->get('status'));
        $this->assertEquals('Maked socket', $socket->get('title'));
        $this->assertIsArray($socket->get('progress'));
        $this->assertTrue($socket->get('progress.enabled'));
        $this->assertEquals(10, $socket->get('progress.maximum'));
        $this->assertEquals(-1, $socket->get('progress.position'));
    }

    /** @test */
    public function create_from_id()
    {
        $socket = Socket::createFromId('sub:maked_socket');
        $this->assertInstanceOf(Socket::class, $socket);
        $this->assertEquals('sub:maked_socket', $socket->get('id'));
        $this->assertEquals(json_encode($this->makedSocket()->data()), json_encode($socket->data()));
    }
}
