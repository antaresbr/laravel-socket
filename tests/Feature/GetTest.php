<?php
namespace Antares\Socket\Tests\Feature;

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

    // /** @test */
    // public function get_successful()
    // {
    //     $response = $this->get(config('socket.route.prefix.api') . '/get/fruits');
    //     $response->assertStatus(200);

    //     $json = $response->json();
    //     $this->assertArrayHasKey('status', $json);
    //     $this->assertEquals('successful', $json['status']);
    //     $this->assertArrayHasKey('data', $json);
    //     $this->assertIsArray($json['data']);

    //     $this->assertArrayHasKey('successful', $json['data']);
    //     $this->assertIsArray($json['data']['successful']);
    //     $this->assertCount(1, $json['data']['successful']);
    //     $this->assertArrayHasKey('fruits', $json['data']['successful']);
    //     $this->assertIsArray($json['data']['successful']['fruits']);
    //     $this->assertCount(4, $json['data']['successful']['fruits']);

    //     $this->assertArrayHasKey('error', $json['data']);
    //     $this->assertIsArray($json['data']['error']);
    //     $this->assertEmpty($json['data']['error']);
    // }
}
