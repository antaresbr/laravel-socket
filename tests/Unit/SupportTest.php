<?php declare(strict_types=1);

namespace Antares\Socket\Tests\Unit;

use Antares\Socket\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SupportTest extends TestCase
{
    private function getPath()
    {
        return ai_socket_path();
    }

    private function getInfos()
    {
        return ai_socket_infos();
    }

    #[Test]
    public function helpers()
    {
        $path = $this->getPath();
        $this->assertIsString($path);
        $this->assertEquals(substr(__DIR__, 0, strlen($path)), $path);

        $infos = $this->getInfos();
        $this->assertIsObject($infos);
    }

    #[Test]
    public function infos()
    {
        $infos = $this->getInfos();
        $this->assertObjectHasProperty('name', $infos);
        $this->assertObjectHasProperty('version', $infos);
        $this->assertObjectHasProperty('major', $infos->version);
        $this->assertObjectHasProperty('release', $infos->version);
        $this->assertObjectHasProperty('minor', $infos->version);
    }
}
