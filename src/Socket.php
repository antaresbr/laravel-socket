<?php
namespace Antares\Socket;

use Antares\Foundation\Arr;
use Carbon\Carbon;

class Socket
{
    const STATUS_UNDEFINED = 'undefined';
    const STATUS_NEW = 'new';
    const STATUS_QUEUED = 'queued';
    const STATUS_WAITING = 'waiting';
    const STATUS_RUNNING = 'running';
    const STATUS_ERROR = 'error';
    const STATUS_CANCELED = 'canceled';
    const STATUS_FINISHED = 'finished';

    /**
     * Socket saved data
     * @var array
     */
    protected $savedData = [];

    /**
     * Access to protected savedData property
     *
     * @return array
     */
    public function savedData(): array
    {
        return $this->savedData;
    }

    /**
     * Socket data
     * @var array
     */
    protected $data = [];

    /**
     * Access to protected data property
     *
     * @return array
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
            'status' => self::STATUS_UNDEFINED,
            'created' => $now->format(config('socket.date_format')),
            'started' => null,
            'finished' => null,
            'seen' => false,
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
     * Load data content from id
     *
     * @param string $id
     * @return array|null
     */
    public function loadContentFromId($id = null): array|null
    {
        $fileName = static::fileName($id ?? $this->get('id'));
        $content = file_exists($fileName) ? file_get_contents($fileName) : null;
        return $content ? json_decode($content, true) : null;
    }

    /**
     * Load data from id
     *
     * @param string $id
     * @return static
     */
    public function loadFromId($id = null): static
    {
        $content = $this->loadContentFromId($id);
        $this->savedData = $content;
        $this->data = $content;
        return $this;
    }

    /**
     * Refresh data
     *
     * @return static
     */
    public function refresh(): static
    {
        $this->loadFromId();
        return $this;
    }

    /**
     * POSIX check if user is in group
     *
     * @param  int $uid
     * @param  int $gid
     * @return bool
     */
    protected static function posixUserInGroup($uid, $gid): bool
    {
        $inGroup = false;

        $user = posix_getpwuid($uid);
        $group = posix_getgrgid($gid);
        if ($user and $group) {
            $inGroup = in_array($user['name'], $group['members']);
        }

        return $inGroup;
    }

    /**
     * Save current object to file
     *
     * @param  bool $force
     * @return static
     */
    public function saveToFile(bool $force = false): static
    {
        if ($force or !$this->isCanceled()) {
            $fileName = static::fileName($this->get('id'));
            $dirName = dirname($fileName);
            if (!empty($dirName) and !is_dir($dirName)) {
                mkdir($dirName);
                chmod($dirName, 0775);
                if (static::posixUserInGroup(getmyuid(), filegroup(dirname($dirName)))) {
                    chgrp($dirName, filegroup(dirname($dirName)));
                }
            }
            if (!is_file($fileName)) {
                touch($fileName);
                chmod($fileName, 0664);
                if (static::posixUserInGroup(getmyuid(), filegroup(dirname($dirName)))) {
                    chgrp($fileName, filegroup(dirname($fileName)));
                }
            }
            $data = json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
            file_put_contents($fileName, $data);
            $this->savedData = $data;
        }
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
        $this->set('started', Carbon::now()->format(config('socket.date_format')));
        $this->status(self::STATUS_RUNNING, $save);
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
        $this->set('finished', Carbon::now()->format(config('socket.date_format')));
        $this->status(self::STATUS_FINISHED, $save);
        return $this;
    }

    /**
     * Define this socket to error state
     *
     * @param boolean $save
     * @return static
     */
    public function error($save = false): static
    {
        $this->set('finished', Carbon::now()->format(config('socket.date_format')));
        $this->status(self::STATUS_ERROR, $save);
        return $this;
    }

    /**
     * Define this socket to canceld state
     *
     * @param boolean $save
     * @return static
     */
    public function cancel($save = false): static
    {
        $this->set('finished', Carbon::now()->format(config('socket.date_format')));
        $this->status(self::STATUS_CANCELED)->saveToFile(true);
        return $this;
    }

    /**
     * Check if socket is canceled
     *
     * @return bool
     */
    public function isCanceled()
    {
        $this->savedData = $this->loadContentFromId() ?? [];
        return ($this->get('status') == self::STATUS_CANCELED or Arr::get($this->savedData, 'status') == self::STATUS_CANCELED);
    }

    //------------------------------
    //-- Safe socket manipulation --
    //------------------------------

    /**
     * Define socket status
     *
     * @param Socket $socket
     * @param string $status
     * @return static
     */
    public static function socketStatus($socket, $status)
    {
        if ($socket) {
            $socket->status($status, true);
        }
        return $socket;
    }

    /**
     * Start a socket with a title and message.
     *
     * @param Socket $socket
     * @param string $title
     * @param string $message
     * @return static
     */
    public static function socketStart($socket, $title, $message)
    {
        if ($socket) {
            $socket->set('title', $title);
            $socket->set('message', $message);
            $socket->start(true);
        }
        return $socket;
    }

    /**
     * Finish a socket.
     *
     * @param Socket $socket
     * @param string $message
     * @param array $files
     * @param array $data
     * @return static
     */
    public static function socketFinish($socket, $message, $files = null, $data = null)
    {
        if ($socket) {
            $socket->set('result.message', $message);
            $socket->set('result.files', $files);
            $socket->set('result.data', $data);
            $socket->finish(true);
        }
        return $socket;
    }

    /**
     * Set the socket error.
     *
     * @param Socket $socket
     * @param string $message
     * @param array $data
     * @return static
     */
    public static function socketError($socket, $message, $data = null)
    {
        if ($socket) {
            $socket->set('result.error', true);
            $socket->set('result.message', $message);
            $socket->set('result.data', $data);
            $socket->error(true);
        }
        return $socket;
    }

    /**
     * Cancel socket.
     *
     * @param Socket $socket
     * @param string $message
     * @param array $data
     * @return static
     */
    public static function socketCancel($socket, $message = null, $data = null)
    {
        if ($socket) {
            is_null($message) or $socket->set('result.message', $message);
            is_null($data) or $socket->set('result.data', $data);
            $socket->cancel(true);
        }
        return $socket;
    }

    /**
     * Check if socket is canceled
     *
     * @param Socket $socket
     * @return bool
     */
    public static function socketIsCanceled(&$socket)
    {
        if ($socket) {
            return $socket->isCanceled();
        }
        return false;
    }

    /**
     * Set the socket confirmation.
     *
     * @param Socket $socket
     * @param string $message
     * @return static
     */
    public static function socketConfirmation($socket, $message)
    {
        if ($socket) {
            $socket->set('confirmation.enabled', true);
            $socket->set('confirmation.message', $message);
            $socket->status(self::STATUS_WAITING, true);
        }
        return $socket;
    }

    /**
     * Set the socket title.
     *
     * @param Socket $socket
     * @param string $title
     * @return static
     */
    public static function socketTitle($socket, $title)
    {
        if ($socket) {
            $socket->set('title', $title, true);
        }
        return $socket;
    }

    /**
     * Set the socket message.
     *
     * @param Socket $socket
     * @param string $message
     * @return static
     */
    public static function socketMessage($socket, $message)
    {
        if ($socket) {
            $socket->set('message', $message, true);
        }
        return $socket;
    }

    /**
     * Increase progress position
     *
     * @param Socket $socket
     * @param int $step
     * @return static
     */
    public static function socketProgressIncrease($socket, $step = 1)
    {
        if ($socket) {
            $key = 'progress.position';
            $socket->set($key, $socket->get($key, 0) + $step, true);
        }
        return $socket;
    }

    /**
     * Set the socket progress position
     *
     * @param Socket $socket
     * @param int $position
     * @return static
     */
    public static function socketProgressPosition($socket, $position)
    {
        if ($socket) {
            $socket->set('progress.position', $position, true);
        }
        return $socket;
    }

    /**
     * Set the socket progress options
     *
     * @param Socket $socket
     * @param int $maximum
     * @param int $position
     * @return static
     */
    public static function socketProgress($socket, $enabled, $maximum = -1, $position = 0)
    {
        if ($socket) {
            $socket->set('progress.enabled', $enabled);
            $socket->set('progress.maximum', $maximum);
            $socket->set('progress.position', $position, true);
        }
        return $socket;
    }
}
