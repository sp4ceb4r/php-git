<?php

namespace Git;

use Process\Command;
use Process\Process;
use Process\ProcessException;
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
        $this->validateBinary($binary);

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
     * @return Process
     * @throws GitException
     */
    public function exec($command, array $args = [], array $options = [], array $paths = [])
    {
        if (in_array($command, static::$subcommands)) {
            list($command, $subcommand) = explode('-', $command);
            array_unshift($args, $subcommand);
        }

        array_unshift($args, $command);

        $cmd = Command::command('git')->withArgs($args)
                                      ->withOptions($options);

        $process = new Process($cmd, $this->project_dir);

        try {
            $process->run();

            return $process;
        } catch (ProcessException $ex) {
            throw new GitException("Error executing [$cmd].", $process, $ex);
        }
    }

    protected function validateBinary($binary)
    {
        if (!is_file($binary) || !is_executable($binary)) {
            throw new LogicException('Invalid configuration. Git improperly configured.');
        }
    }

    public function getDir()
    {
        return $this->project_dir;
    }
}
