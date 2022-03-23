<?php
namespace Antares\Socket;

use Antares\Support\Arr;
use Carbon\Carbon;

class Socket
{
    /**
     * Socket data
     * @var array
     */
    protected $data = [];

    /**
     * Access to protected data property
     *
     * @return void
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Get data property in dot notation
     *
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    /**
     * Set data property value in dot notation
     *
     * @param mixed $key
     * @param mixed $value
     * @param boolean $saveToFile
     * @return array
     */
    public function set($key, $value, $saveToFile = false): array
    {
        if ($key != 'id' and $key != 'file') {
            Arr::set($this->data, $key, $value);
            if ($saveToFile) {
                $this->saveToFile();
            }
        }
        return $this->data;
    }

    /**
     * Class Constructor
     *
     * @param string $prefix
     * @param string $id
     */
    public function __construct($prefix = null, $id = null)
    {
        $now = Carbon::now(config('app.timezone'));
        $id = $id ?: $now->format('Y-m-d') . '_' . $now->format('H\hi\ms\s') . '_' . static::randomStr(config('socket.randomId', 32));
        if ($prefix) {
            $id = $prefix . ':' . $id;
        }

        $this->data = [
            'id' => $id,
            'user' => null,
            'status' => 'undefined',
            'created' => $now->format('Y-m-d H:i:s e'),
            'started' => null,
            'finished' => null,
            'title' => null,
            'message' => null,
            'progress' => [
                'enabled' => false,
                'maximum' => -1,
                'position' => -1,
            ],
            'result' => [
                'error' => false,
                'message' => -1,
                'files' => [],
                'data' => [],
            ],
            'confirmation' => [
                'enabled' => false,
                'message' => null,
                'options' => [],
                'answer' => null,
            ],
        ];
    }

    /**
     * Create a randomic string
     *
     * @param integer $length
     * @return string
     */
    public static function randomStr($length = 16): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $maxRand = strlen($chars) - 1;
        $rs = '';
        for ($i = 1; $i <= $length; $i++) {
            $rs .= substr($chars, mt_rand(0, $maxRand), 1);
        }
        return $rs;
    }

    /**
     * Get full file name
     *
     * @param string $id
     * @return string|null
     */
    public static function fileName($id): string|null
    {
        if (empty($id)) {
            return null;
        }
        $id = str_replace(['..', '\\', ';', '"', "'"], '', $id);
        $id = str_replace(':', DIRECTORY_SEPARATOR, $id);
        return config('socket.data') . DIRECTORY_SEPARATOR . $id . '.json';
    }

    /**
     * Make a brand new instance
     *
     * @param array $options
     * @return static
     */
    public static function make($options = []): static
    {
        $prefix = isset($options['prefix']) ? $options['prefix'] : null;
        $id = isset($options['id']) ? $options['id'] : null;
        $instance = new static($prefix, $id);

        foreach (array_keys(Arr::dot($instance->data)) as $key) {
            if (Arr::has($options, $key)) {
                $instance->set($key, Arr::get($options, $key));
            }
        }

        return $instance->saveToFile();
    }

    /**
     * Create new instance from id
     *
     * @param string $id
     * @return static
     */
    public static function createFromId($id): static|null
    {
        if (empty($id) or !file_exists(static::fileName($id))) {
            return null;
        }

        $instance = new static;
        $instance->loadFromId($id);

        return $instance;
    }

    /**
     * Load data from id
     *
     * @param string $id
     * @return static
     */
    public function loadFromId($id): static
    {
        $this->data = json_decode(file_get_contents(static::fileName($id)), true);
        return $this;
    }

    /**
     * Save current object to file
     *
     * @return static
     */
    public function saveToFile(): static
    {
        $fileName = static::fileName($this->get('id'));
        $dirName = dirname($fileName);
        if (!empty($dirName) and !is_dir($dirName)) {
            mkdir($dirName, 0755, true);
        }
        $data = json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
        file_put_contents($fileName, $data);
        return $this;
    }

    /**
     * Define this socket status
     *
     * @param string $value
     * @param boolean $save
     * @return static
     */
    public function status($value, $save = false): static
    {
        $this->set('status', $value, $save);
        return $this;
    }

    /**
     * Define this socket to running state
     *
     * @param boolean $save
     * @return static
     */
    public function start($save = false): static
    {
        $this->set('started', Carbon::now()->format('Y-m-d H:i:s e'));
        $this->status('running', $save);
        return $this;
    }

    /**
     * Define this socket to finished state
     *
     * @param boolean $save
     * @return static
     */
    public function finish($save = false): static
    {
        $this->set('finished', Carbon::now()->format('Y-m-d H:i:s e'));
        $this->status('finished', $save);
        return $this;
    }

    //------------------------------
    //-- Safe socket manipulation --
    //------------------------------

    /**
     * Define socket status
     *
     * @param Socket $socket
     * @param string $status
     */
    public static function socketStatus($socket, $status)
    {
        if ($socket) {
            $socket->status($status, true);
        }
    }

    /**
     * Start a socket with a title and message.
     *
     * @param Socket $socket
     * @param string $title
     * @param string $message
     */
    public static function socketStart($socket, $title, $message)
    {
        if ($socket) {
            $socket->set('title', $title);
            $socket->set('message', $message);
            $socket->start(true);
        }
    }

    /**
     * Finish a socket.
     *
     * @param Socket $socket
     * @param string $message
     * @param array $files
     * @param array $data
     */
    public static function socketFinish($socket, $message, $files = null, $data = null)
    {
        if ($socket) {
            $socket->set('result.message', $message);
            $socket->set('result.files', $files);
            $socket->set('result.data', $data);
            $socket->finish(true);
        }
    }

    /**
     * Set the socket error.
     *
     * @param Socket $socket
     * @param string $message
     * @param array $data
     */
    public static function socketError($socket, $message, $data = null)
    {
        if ($socket) {
            $socket->set('result.error', true);
            $socket->set('result.message', $message);
            $socket->set('result.data', $data);
            $socket->finish(true);
        }
    }

    /**
     * Set the socket confirmation.
     *
     * @param Socket $socket
     * @param string $message
     */
    public static function socketConfirmation($socket, $message)
    {
        if ($socket) {
            $socket->set('confirmation.enabled', true);
            $socket->set('confirmation.message', $message);
            $socket->status('waiting confirmation', true);
        }
    }

    /**
     * Set the socket message.
     *
     * @param Socket $socket
     * @param string $message
     */
    public static function socketMessage($socket, $message)
    {
        if ($socket) {
            $socket->set('message', $message, true);
        }
    }

    /**
     * Increase progress position
     *
     * @param Socket $socket
     * @param int $step
     */
    public static function socketProgressIncrease($socket, $step = 1)
    {
        if ($socket) {
            $key = 'progress.position';
            $socket->set($key, $socket->get($key, 0) + $step, true);
        }
    }

    /**
     * Set the socket progress position
     *
     * @param Socket $socket
     * @param int $position
     */
    public static function socketProgressPosition($socket, $position)
    {
        if ($socket) {
            $socket->set('progress.position', $position, true);
        }
    }

    /**
     * Set the socket progress options
     *
     * @param Socket $socket
     * @param int $maximum
     * @param int $position
     */
    public static function socketProgress($socket, $enabled, $maximum = -1, $position = 0)
    {
        if ($socket) {
            $socket->set('progress.enabled', $enabled);
            $socket->set('progress.maximum', $maximum);
            $socket->set('progress.position', $position, true);
        }
    }
}
