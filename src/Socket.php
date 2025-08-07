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
    const STATUS_DELETED = 'deleted';
    const STATUS_SUCCESSFUL = 'successful';

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
     * @return static
     */
    public function set($key, $value, $saveToFile = false): static
    {
        if ($key != 'id' and $key != 'file') {
            Arr::set($this->data, $key, $value);
            if ($saveToFile) {
                $this->saveToFile();
            }
        }
        return $this;
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
                'message' => null,
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
     * @param string $suffix
     * @return string|null
     */
    public static function fileName($id, $suffix = null): string|null
    {
        if (empty($id)) {
            return null;
        }
        $id = str_replace(['..', '\\', ';', '"', "'"], '', $id);
        $id = str_replace(':', DIRECTORY_SEPARATOR, $id);
        return config('socket.data') . DIRECTORY_SEPARATOR . $id . ($suffix ? "_{$suffix}" : '') . '.json';
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
     * @param  string $suffix
     * @return static
     */
    public function saveToFile(bool $force = false, string $suffix = ''): static
    {
        if ($force or $this->isactive()) {
            $fileName = static::fileName($this->get('id'), $suffix);
            $dirName = dirname($fileName);
            if (!empty($dirName) and !is_dir($dirName)) {
                mkdir($dirName, 0775, true);
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
            $this->savedData = $this->data;
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
     * Check whether socket is inactive
     *
     * @return bool
     */
    public function isInactive()
    {
        $id = $this->get('id');
        return (
            is_file(static::fileName($id, self::STATUS_DELETED)) or
            is_file(static::fileName($id, self::STATUS_ERROR)) or
            is_file(static::fileName($id, self::STATUS_CANCELED)) or
            is_file(static::fileName($id, self::STATUS_SUCCESSFUL))
        );
    }

    /**
     * Check whether socket is active
     *
     * @return bool
     */
    public function isActive()
    {
        return !$this->isInactive();
    }

    /**
     * Check if socket is in specific status
     *
     * @param string $status
     * @return bool
     */
    public function statusIs($status)
    {
        $s = $this->get('status');
        if (empty($s)) {
            $s = Arr::get($this->savedData, 'status');
        }
        $r = ($s == $status);

        $id = $this->get('id');
        $isDeleted = is_file(static::fileName($id, self::STATUS_DELETED));
        
        if ($status == self::STATUS_DELETED) {
            return $r or $isDeleted;
        }
        
        if ($status == self::STATUS_ERROR) {
            return $r or (!$isDeleted and is_file(static::fileName($id, self::STATUS_ERROR)));
        }
        
        if ($status == self::STATUS_CANCELED) {
            return $r or (!$isDeleted and is_file(static::fileName($id, self::STATUS_CANCELED)));
        }
        
        if ($status == self::STATUS_SUCCESSFUL) {
            return $r or (!$isDeleted and is_file(static::fileName($id, self::STATUS_SUCCESSFUL)));
        }

        return $r;
    }

    /**
     * Define this socket to running state
     *
     * @param mixed $title
     * @param mixed $message
     * @param boolean $save
     * @return static
     */
    public function start($title = null, $message = null, $save = false): static
    {
        if ($title !== null) {
            $this->set('title', $title);
        }
        if ($message !== null) {
            $this->set('message', $message);
        }
        $this->set('started', Carbon::now()->format(config('socket.date_format')));
        $this->status(self::STATUS_RUNNING, $save);
        return $this;
    }

    /**
     * Define this socket to successful state
     *
     * @param mixed $message
     * @param mixed $data
     * @param mixed $files
     * @return static
     */
    public function successful($message = null, $data = null, $files = null): static
    {
        if ($this->isActive()) {
            $this->set('result.error', false);
            if ($message !== null) {
                $this->set('result.message', $message);
            }
            if ($data !== null) {
                $this->set('result.data', $data);
            }
            if ($files !== null) {
                $this->set('result.files', $files);
            }
            $this->set('finished', Carbon::now()->format(config('socket.date_format')));
            $this->set('message', _('Completed successfully'));
            $this->status(self::STATUS_SUCCESSFUL);
            $this->saveToFile(true);
            $this->saveToFile(true, self::STATUS_SUCCESSFUL);
        }
        return $this;
    }

    /**
     * Check if socket is successful
     *
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->statusIs(self::STATUS_SUCCESSFUL);
    }

    /**
     * Define this socket to error state
     *
     * @param mixed $message
     * @param mixed $data
     * @return static
     */
    public function error($message = null, $data = null): static
    {
        if ($this->isActive()) {
            $this->set('result.error', true);
            if ($message !== null) {
                $this->set('result.message', $message);
            }
            if ($data !== null) {
                $this->set('result.data', $data);
            }
            $this->set('finished', Carbon::now()->format(config('socket.date_format')));
            $this->set('message', _('Completed with error'));
            $this->status(self::STATUS_ERROR);
            $this->saveToFile(true);
            $this->saveToFile(true, self::STATUS_ERROR);
        }
        return $this;
    }

    /**
     * Check if socket has error
     *
     * @return bool
     */
    public function hasError()
    {
        return $this->statusIs(self::STATUS_ERROR);
    }

    /**
     * Define this socket to canceld state
     *
     * @param mixed $message
     * @param mixed $data
     * @return static
     */
    public function cancel($message = null, $data = null): static
    {
        if ($this->isActive()) {
            $this->set('result.error', true);
            if ($message !== null) {
                $this->set('result.message', $message);
            }
            if ($data !== null) {
                $this->set('result.data', $data);
            }
            $this->set('finished', Carbon::now()->format(config('socket.date_format')));
            $this->set('message', _('Canceled by user'));
            $this->status(self::STATUS_CANCELED);
            $this->saveToFile(true);
            $this->saveToFile(true, self::STATUS_CANCELED);
        }
        return $this;
    }

    /**
     * Check if socket is canceled
     *
     * @return bool
     */
    public function isCanceled()
    {
        return $this->statusIs(self::STATUS_CANCELED);
    }

    /**
     * Define this socket to deleted state
     *
     * @param mixed $message
     * @param mixed $data
     * @return static
     */
    public function delete($message = null, $data = null): static
    {
        if (!$this->isDeleted()) {
            if ($message !== null) {
                $this->set('result.message', $message);
            }
            if ($data !== null) {
                $this->set('result.data', $data);
            }
            $this->set('finished', Carbon::now()->format(config('socket.date_format')));
            $this->set('message', _('Deleted from the system'));
            $this->status(self::STATUS_DELETED);
            $this->saveToFile(true);
            $this->saveToFile(true, self::STATUS_DELETED);
        }
        return $this;
    }

    /**
     * Check if socket is deleted
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->statusIs(self::STATUS_DELETED);
    }

    //------------------------------
    //-- Safe socket manipulation --
    //------------------------------

    /**
     * Refresh socket data
     *
     * @param Socket $socket
     * @return static
     */
    public static function socketRefresh($socket)
    {
        if ($socket) {
            $socket->refresh();
        }
        return $socket;
    }

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
     * Check if socket is inactive
     *
     * @param Socket $socket
     * @return bool
     */
    public static function socketIsInactive($socket)
    {
        if ($socket) {
            return $socket->isInactive();
        }
        return false;
    }

    /**
     * Check if socket is active
     *
     * @param Socket $socket
     * @return bool
     */
    public static function socketIsActive($socket)
    {
        if ($socket) {
            return $socket->isActive();
        }
        return false;
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
            $socket->start($title, $message, true);
        }
        return $socket;
    }

    /**
     * Finish a socket.
     *
     * @param Socket $socket
     * @param string $message
     * @param array $data
     * @param array $files
     * @return static
     */
    public static function socketSuccessful($socket, $message = null, $data = null, $files = null)
    {
        if ($socket) {
            $socket->successful($message, $data, $files);
        }
        return $socket;
    }

    /**
     * Check if socket is finished
     *
     * @param Socket $socket
     * @return bool
     */
    public static function socketIsSuccessful($socket)
    {
        if ($socket) {
            return $socket->isSuccessful();
        }
        return false;
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
            $socket->error($message, $data);
        }
        return $socket;
    }

    /**
     * Check if socket has error
     *
     * @param Socket $socket
     * @return bool
     */
    public static function socketHasError($socket)
    {
        if ($socket) {
            return $socket->hasError();
        }
        return false;
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
            $socket->cancel($message, $data);
        }
        return $socket;
    }

    /**
     * Check if socket is canceled
     *
     * @param Socket $socket
     * @return bool
     */
    public static function socketIsCanceled($socket)
    {
        if ($socket) {
            return $socket->isCanceled();
        }
        return false;
    }

    /**
     * Delete socket.
     *
     * @param Socket $socket
     * @param string $message
     * @param array $data
     * @return static
     */
    public static function socketDelete($socket, $message = null, $data = null)
    {
        if ($socket) {
            $socket->delete($message, $data);
        }
        return $socket;
    }

    /**
     * Check if socket is deleted
     *
     * @param Socket $socket
     * @return bool
     */
    public static function socketIsDeleted($socket)
    {
        if ($socket) {
            return $socket->isDeleted();
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
}
