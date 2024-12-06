<?php
namespace Antares\Socket\Tests\Feature;

use Antares\Foundation\Arr;
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

        $socket->status(Socket::STATUS_NEW);
        $this->assertEquals(Socket::STATUS_NEW, $socket->get('status'));

        $socket->set('title', 'New socket title');
        $this->assertEquals('New socket title', $socket->get('title'));

        $this->assertFalse($socket->get('progress.enabled'));
        $socket->set('progress.enabled', true);
        $this->assertTrue($socket->get('progress.enabled'));

        $socket->set('result.files', ['file-a', 'file-b', 'file-c']);
        $this->assertIsArray($socket->get('result.files'));
        $this->assertCount(3, $socket->get('result.files'));
    }

    /** @test */
    public function socket_safe_data_manipulation()
    {
        $socket = new Socket();

        Socket::socketStatus(null, 'new status');
        $this->assertEquals(Socket::STATUS_UNDEFINED, $socket->get('status'));

        //dd($socket);

        Socket::socketStatus($socket, Socket::STATUS_NEW);
        $this->assertEquals(Socket::STATUS_NEW, $socket->get('status'));

        Socket::socketStart(null, 'new title', 'new message');
        $this->assertNull($socket->get('title'));
        $this->assertNull($socket->get('message'));
        $this->assertNull($socket->get('started'));

        Socket::socketStart($socket, 'new title', 'new message');
        $this->assertEquals('new title', $socket->get('title'));
        $this->assertEquals('new message', $socket->get('message'));
        $this->assertNotNull($socket->get('started'));
    }

    protected $socket;

    protected function makedSocket()
    {
        if (!$this->socket) {
            $this->socket = Socket::make([
                'id' => 'sub:maked_socket',
                'status' => Socket::STATUS_NEW,
                'title' => 'Maked socket',
                'progress' => [
                    'enabled' => true,
                    'maximum' => 10,
                ],
            ]);
        }
        return $this->socket;
    }

    /** @test */
    public function make_socket()
    {
        $socket = $this->makedSocket();

        $this->assertInstanceOf(Socket::class, $socket);
        $this->assertStringStartsWith('sub:', $socket->get('id'));
        $this->assertEquals(Socket::STATUS_NEW, $socket->get('status'));
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
        //-- assure created is the same
        $socket->set('created', $this->makedSocket()->get('created'));
        $this->assertEquals(json_encode($this->makedSocket()->data()), json_encode($socket->data()));
    }

    /** @test */
    public function cancel_socket()
    {
        $socket1 = new Socket();
        $socket1->set('status', Socket::STATUS_NEW);
        $socket1->saveToFile();

        $socket2 = Socket::createFromId($socket1->get('id'));
        $this->assertEquals($socket1->data(), $socket2->data());

        Socket::socketProgress($socket1, true, 10);
        $this->assertEquals(10, $socket1->get('progress.maximum'));
        
        Socket::socketProgressIncrease($socket1, 3);
        $this->assertFalse($socket1->isCanceled());
        $this->assertEquals(3, $socket1->get('progress.position'));
        
        $socket1->set('result.message', 'Result message');
        $socket1->set('result.data', ['Result data']);
        $socket2->refresh();

        Socket::socketCancel($socket1);
        $this->assertTrue($socket1->isCanceled());
        $this->assertEquals($socket1->data(), $socket1->savedData());
        $this->assertEquals('Result message', $socket1->get('result.message'));
        $this->assertEquals(['Result data'], $socket1->get('result.data'));
        
        Socket::socketProgressIncrease($socket2, 2);
        Socket::socketFinish($socket2, 'Successful');
        $this->assertTrue($socket2->isCanceled());
        $this->assertNotEquals($socket2->data(), $socket2->savedData());
        $this->assertEquals(5, $socket2->get('progress.position'));
        $this->assertEquals(Socket::STATUS_FINISHED, $socket2->get('status'));
        $this->assertEquals(3, Arr::get($socket2->savedData(), 'progress.position'));
        $this->assertEquals(Socket::STATUS_CANCELED, Arr::get($socket2->savedData(), 'status'));

        Socket::socketCancel($socket2, 'Socket2 message', ['Socket2 data']);
        $this->assertTrue($socket2->isCanceled());
        $this->assertEquals($socket2->data(), $socket2->savedData());
        $this->assertEquals('Socket2 message', $socket2->get('result.message'));
        $this->assertEquals(['Socket2 data'], $socket2->get('result.data'));
    }
}
