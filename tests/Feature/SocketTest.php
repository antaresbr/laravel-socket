<?php
namespace Antares\Socket\Tests\Feature;

use Antares\Foundation\Arr;
use Antares\Socket\Socket;
use Antares\Socket\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

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

    private function new_socket()
    {
        $socket = new Socket();
        $socket->set('status', Socket::STATUS_NEW);
        $socket->saveToFile();

        Socket::socketProgress($socket, true, 10);
        $this->assertEquals(10, $socket->get('progress.maximum'));
        
        Socket::socketProgressIncrease($socket, 3);
        $this->assertEquals(3, $socket->get('progress.position'));
        $this->assertFalse($socket->hasError());
        $this->assertTrue($socket->isActive());
        
        $socket->set('result.message', 'Result message');
        $socket->set('result.data', ['Result data']);
        $this->assertEquals('Result message', $socket->get('result.message'));
        $this->assertEquals(['Result data'], $socket->get('result.data'));

        return $socket;
    }

    /** @test */
    public function successful_socket_and_delete()
    {
        $socket = $this->new_socket();

        Socket::socketSuccessful($socket, 'Successful message', ['Successful data'], ['file1.txt', 'file2.txt']);
        $this->assertTrue($socket->isSuccessful());
        $this->assertTrue($socket->isInactive());
        $this->assertFalse($socket->isActive());
        $this->assertEquals($socket->data(), $socket->savedData());
        $this->assertEquals('Successful message', $socket->get('result.message'));
        $this->assertEquals(['Successful data'], $socket->get('result.data'));
        $this->assertEquals(['file1.txt', 'file2.txt'], $socket->get('result.files'));

        $socket->set('status', Socket::STATUS_RUNNING)->saveToFile();
        $this->assertTrue($socket->isSuccessful());
        $this->assertTrue($socket->isInactive());
        $this->assertFalse($socket->isActive());

        $this->do_delete_socket($socket);
    }

    /** @test */
    public function error_socket_and_delete()
    {
        $socket = $this->new_socket();

        Socket::socketError($socket, 'Error message', ['Error data']);
        $this->assertTrue($socket->hasError());
        $this->assertTrue($socket->isInactive());
        $this->assertFalse($socket->isActive());
        $this->assertEquals($socket->data(), $socket->savedData());
        $this->assertEquals('Error message', $socket->get('result.message'));
        $this->assertEquals(['Error data'], $socket->get('result.data'));

        $socket->set('status', Socket::STATUS_RUNNING)->saveToFile();
        $this->assertTrue($socket->hasError());
        $this->assertTrue($socket->isInactive());
        $this->assertFalse($socket->isActive());

        $this->do_delete_socket($socket);
    }

    /** @test */
    public function cancel_socket_and_delete()
    {
        $socket = $this->new_socket();
        
        Socket::socketCancel($socket, 'Cancel message', ['Cancel data']);
        $this->assertTrue($socket->isCanceled());
        $this->assertTrue($socket->isInactive());
        $this->assertFalse($socket->isActive());
        $this->assertEquals($socket->data(), $socket->savedData());
        $this->assertEquals('Cancel message', $socket->get('result.message'));
        $this->assertEquals(['Cancel data'], $socket->get('result.data'));

        $socket->set('status', Socket::STATUS_RUNNING)->saveToFile();
        $this->assertTrue($socket->isCanceled());
        $this->assertTrue($socket->isInactive());
        $this->assertFalse($socket->isActive());

        $this->do_delete_socket($socket);
    }

    private function do_delete_socket($socket) {
        Socket::socketDelete($socket, 'Delete message', ['Delete data']);
        $this->assertTrue($socket->isDeleted());
        $this->assertTrue($socket->isInactive());
        $this->assertFalse($socket->isActive());
        $this->assertEquals($socket->data(), $socket->savedData());
        $this->assertEquals('Delete message', $socket->get('result.message'));
        $this->assertEquals(['Delete data'], $socket->get('result.data'));
    }

    /** @test */
    public function delete_socket()
    {
        $socket = $this->new_socket();

        $this->do_delete_socket($socket);

        $socket->set('status', Socket::STATUS_RUNNING)->saveToFile();
        $this->assertTrue($socket->isDeleted());
        $this->assertTrue($socket->isInactive());
        $this->assertFalse($socket->isActive());
    }

    /** @test */
    public function locale_successful_socket()
    {
        $socket = $this->new_socket();
        Socket::socketSuccessful($socket, 'Successful message', ['Successful data'], ['file1.txt', 'file2.txt']);
        $this->assertEquals('Completed successfully', $socket->get('message'));
        
        $this->app->setLocale('pt_BR');
        $this->assertEquals('pt_BR', $this->app->getLocale());

        $socket = $this->new_socket();
        Socket::socketSuccessful($socket, 'Successful message', ['Successful data'], ['file1.txt', 'file2.txt']);
        $this->assertEquals('Concluído com sucesso', $socket->get('message'));
    }

    /** @test */
    public function locale_error_socket()
    {
        $socket = $this->new_socket();
        Socket::socketError($socket, 'Error message', ['Error data']);
        $this->assertEquals('Completed with error', $socket->get('message'));
        
        $this->app->setLocale('pt_BR');
        $this->assertEquals('pt_BR', $this->app->getLocale());

        $socket = $this->new_socket();
        Socket::socketError($socket, 'Error message', ['Error data']);
        $this->assertEquals('Concluído com erro', $socket->get('message'));
    }

    /** @test */
    public function locale_cancel_socket()
    {
        $socket = $this->new_socket();
        Socket::socketCancel($socket, 'Cancel message', ['Cancel data']);
        $this->assertEquals('Canceled by user', $socket->get('message'));
        
        $this->app->setLocale('pt_BR');
        $this->assertEquals('pt_BR', $this->app->getLocale());

        $socket = $this->new_socket();
        Socket::socketCancel($socket, 'Cancel message', ['Cancel data']);
        $this->assertEquals('Cancelado pelo usuário', $socket->get('message'));
    }

    /** @test */
    public function locale_delete_socket()
    {
        $socket = $this->new_socket();
        $this->do_delete_socket($socket);
        $this->assertEquals('Deleted from the system', $socket->get('message'));
        
        $this->app->setLocale('pt_BR');
        $this->assertEquals('pt_BR', $this->app->getLocale());

        $socket = $this->new_socket();
        $this->do_delete_socket($socket);
        $this->assertEquals('Excluído do sistema', $socket->get('message'));
    }
}
