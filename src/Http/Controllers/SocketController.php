<?php
namespace Antares\Socket\Http\Controllers;

use Antares\Socket\Socket;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class SocketController extends Controller
{
    /**
     * Get socket file
     *
     * @param string $id
     * @return \Illuminate\Http\Response
     */
    public function get($id)
    {
        $socket = Socket::createFromId(str_replace(['..', '\\', ';', '"', "'"], '', $id));
        if (!$socket) {
            return new JsonResponse(null, 404);
        }
        return new JsonResponse($socket->data());
    }
}
