<?php

namespace Git;

use Closure;
use Shell\Commands\Command;
use Shell\Process;
use Shell\Exceptions\ProcessException;
use LogicException;


/**
 * Class Git
 */
class Git
{
    /**
     * @var string
     */
    protected static $default = '/usr/bin/git';

    /**
     * @var array
     */
    protected static $subcommands = [
        'remote-add',
        'remote-remove',
        'remote-rename',
        'stash-pop',
    ];

    /**
     * @var string
     */
    protected $binary = '/usr/bin/git';

    /**
     * @var string
     */
    protected $project_dir;

    /**
     * @var bool
     */
    protected $booted = false;

    /**
     * Set the configuration option globally (for executing user).
     *
     * @param $key
     * @param $value
     */
    public static function configureGlobal($key, $value)
    {
    }

    /**
     * Verify the git binary exists and return the absolute path.
     *
     * @return null|string
     */
    protected static function findBinary()
    {
        if (is_file(static::$default) && is_executable(static::$default)) {
            return static::$default;
        }

        foreach (explode(':', $_SERVER['path']) as $path) {
            $binary = "$path/git";
            if (is_file($binary) && is_executable($binary)) {
                return $binary;
            }
        }

        return null;
    }

    /**
     * Validate the git binary exists and is executable.
     *
     * @param $binary
     */
    protected static function validateBinary($binary)
    {
        if (!is_file($binary) || !is_executable($binary)) {
            throw new LogicException('Invalid configuration. Git improperly configured.');
        }
    }

    /**
     * Git constructor.
     *
     * @param $project_dir
     * @param null $binary
     */
    public function __construct($project_dir, $binary = null)
    {
        if (is_null($binary)) {
            $binary = static::$default;
        }
        static::validateBinary($binary);

        $this->binary = $binary;
        $this->project_dir = $project_dir;
    }

    /**
     * Execute the command.
     *
     * @param $command
     * @param array $args
     * @param array $options
     * @param array $paths
     * @param Closure $onSuccess
     * @param Closure $onError
     * @return Process
     * @throws GitException
     */
    public function exec($command, array $args = [], array $options = [], array $paths = [], Closure $onSuccess = null, Closure $onError = null)
    {
        $process = $this->buildProcess($command, $args, $options, $paths, $onSuccess, $onError);

        try {
            $process->run();

            return $process;
        } catch (ProcessException $ex) {
            $code = $process->getSignal();
            throw new GitException("$process failed.", $code ?: 0, $ex);
        }
    }

    public function execNonBlocking($command, array $args = [], array $options = [], array $paths = [], Closure $onSuccess = null, Closure $onError = null)
    {
        $process = $this->buildProcess($command, $args, $options, $paths, $onSuccess, $onError);

        try {
            $process->run(false);

            return $process;
        } catch (ProcessException $ex) {
            $code = $process->getSignal();
            throw new GitException("$process failed.", $code ?: 0, $ex);
        }
    }

    /**
     * Get the project directory.
     *
     * @return string
     */
    public function getProjectDirectory()
    {
        return $this->project_dir;
    }

    /**
     * @param $command
     * @param array $args
     * @param array $options
     * @param array $paths
     * @param Closure $onSuccess
     * @param Closure $onError
     * @return Process
     */
    private function buildProcess($command, array $args = [], array $options = [], array $paths = [], Closure $onSuccess = null, Closure $onError = null)
    {
        if (in_array($command, static::$subcommands)) {
            list($command, $subcommand) = explode('-', $command);
            array_unshift($args, $subcommand);
        }

        array_unshift($args, $command);

        if (!empty($paths)) {
            $options['--'] = $paths;
        }

        $cmd = Command::make('git')->withArgs($args)
                                   ->withOptions($options);

        return Process::make($cmd)->usingCwd($this->project_dir)
                                  ->onError($onError)
                                  ->onSuccess($onSuccess);
    }
}
