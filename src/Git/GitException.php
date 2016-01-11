<?php

namespace Git;

use Exception;
use Process\Process;


/**
 * Class GitException
 */
class GitException extends Exception
{
    /**
     * @var Process
     */
    protected $process;

    /**
     * GitException constructor.
     *
     * @param string $message
     * @param Process $process
     * @param Exception $prev
     */
    public function __construct($message = '', Process $process = null, Exception $prev = null)
    {
        parent::__construct($message, 0, $prev);

        $this->process = $process;
    }

    /**
     * Get Git command control process.
     *
     * @return Process
     */
    public function getProcess()
    {
        return $this->process;
    }
}