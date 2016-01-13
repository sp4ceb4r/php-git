<?php

namespace Git;

use InvalidArgumentException;
use Shell\Output\DefaultOutputHandler;
use Shell\Process;
use Symfony\Component\Filesystem\Filesystem;


/**
 * Class GitRepository
 */
class GitRepository
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var Git
     */
    protected $git;

    /**
     * @var \Shell\Output\ProcessOutputInterface
     */
    protected $output;

    /**
     * @var bool
     */
    protected $initialized = false;

    /**
     * Initialize a new git repository.
     *
     * @param string $project_dir
     * @param string $remote_url
     * @param \Shell\Output\ProcessOutputInterface $output
     * @return GitRepository
     * @throws GitException
     */
    public static function init($project_dir, $remote_url = null, $output = null)
    {
        $fs = new Filesystem();
        $fs->mkdir($project_dir);

        $git = new Git($project_dir);
        $git->exec('init');

        $repo = new GitRepository($git, $output);

        if (!is_null($remote_url)) {
            $repo->addRemote('origin', $remote_url);
            $repo->setUpstream();

            $repo->config['remotes'][] = [
                'url' => $remote_url,
                'name' => 'origin',
            ];

            $repo->initialized = true;
        }

        return $repo;
    }

    /**
     * Clone a repository (clone is a reserved keyword).
     *
     * @param string $project_dir
     * @param string $remote_url
     * @param null $repo
     * @param \Shell\Output\ProcessOutputInterface $output
     * @param bool $background
     * @return GitRepository|Process
     * @throws GitException
     */
    public static function copy($project_dir, $remote_url, &$repo = null, $output = null, $background = false)
    {
        $fs = new Filesystem();
        $fs->mkdir($project_dir);

        $git = new Git($project_dir);
        $repo = new GitRepository($git, $output);

        $args = [
            $remote_url,
            $project_dir,
        ];

        if ($background) {
            $onSuccess = function () use (&$repo) {
                $repo->processGitConfig();
                $repo->setUpstream();

                $repo->initialized = true;
            };

            return $git->execNonBlocking('clone', $args, [], [], $onSuccess);
        } else {
            $git->exec('clone', $args, ['--verbose' => true]);

            $repo->processGitConfig();
            $repo->setUpstream();

            $repo->initialized = true;

            return $repo;
        }
    }

    /**
     * Open existing repository.
     *
     * @param $project_dir
     * @param \Shell\Output\ProcessOutputInterface $output
     * @return GitRepository
     */
    public static function open($project_dir, $output = null)
    {
        if (!is_dir($project_dir)) {
            throw new InvalidArgumentException("[$project_dir] not found.");
        }

        $config = "$project_dir/.git/config";
        if (!is_file($config)) {
            throw new InvalidArgumentException("[$project_dir] is not a git repo.");
        }

        return new GitRepository(new Git($project_dir), $output);
    }

    /**
     * GitRepository constructor.
     *
     * @param Git $git
     * @param \Shell\Output\ProcessOutputInterface $output
     */
    public function __construct(Git $git, $output = null)
    {
        $this->git = $git;

        $output = is_null($output) ? $git->getOutputHandler() : $output;

        $this->output = $output;
        $this->git->setOutputHandler($output);

        $this->processGitConfig();
    }

    /**
     * List available branches.
     *
     * @return array
     * @throws GitException
     */
    public function listBranches()
    {
        $trim = function ($line) {
            return trim(trim($line, '*'));
        };

        $this->git->exec('branch');

        return array_map($trim, $this->output->readStdoutLines());
    }

    /**
     * Get the current branch for the repository.
     *
     * @return string
     * @throws GitException
     */
    public function currentBranch()
    {
        $this->git->exec('rev-parse', [], ['--abbrev-ref' => 'HEAD']);

        return end($this->output->readStdoutLines());
    }

    /**
     * Delete a branch locally and optionally remotely.
     *
     * @param $branch
     * @param bool $force
     * @param bool $remote
     * @return string
     * @throws GitException
     */
    public function deleteBranch($branch, $force = true, $remote = false)
    {
        $key = '-D';
        if (!$force) {
            $key = '-d';
        }

        $process = $this->git->exec('branch', [], [$key => $branch]);

        if ($remote) {
            unset($process);
            $process = $this->git->exec('push', ['origin', ":$branch"]);
        }
    }

    /**
     * Set configuration option locally.
     *
     * @param $key
     * @param $value
     * @return void
     */
    public function configure($key, $value)
    {
    }

    /**
     * Checkout the desired point in time.
     *
     * @param string $refspec
     * @param bool $createBranch
     * @return void
     */
    public function checkout($refspec, $createBranch = false)
    {
    }

    /**
     * Fetch upstream changes without applying.
     *
     * @return void
     */
    public function fetch()
    {
    }

    /**
     * Pull (and apply) upstream changes.
     *
     * @return void
     */
    public function pull()
    {
    }

    /**
     * Set the branch tracking information.
     *
     * @param string $remote
     * @param string $branch
     * @return void
     * @throws GitException
     */
    public function setUpstream($remote = 'origin', $branch = null)
    {
        $args = [$remote];
        if (!is_null($branch)) {
            array_push($args, $branch);
        }

        $options = ['-u'];

        $this->git->exec('push', $args, $options);
    }

    /**
     * Stash local changes.
     *
     * @return void
     * @throws GitConfigException
     * @throws GitException
     */
    public function stash()
    {
        if (!$this->isUserConfigured()) {
            throw new GitConfigException('No user defined.');
        }

        $this->git->exec('stash');
    }

    /**
     * Pop stashed changes.
     *
     * @return void
     * @throws GitException
     */
    public function pop()
    {
        $this->git->exec('stash-pop');
    }

    /**
     * List existing remotes.
     *
     * @param bool $verbose
     * @return void
     * @throws GitException
     */
    public function listRemotes($verbose = false)
    {
        $options = [];
        if ($verbose) {
            $options['-v'] = null;
        }

        $this->git->exec('remote', [], $options);
    }

    /**
     * Add new remote.
     *
     * @param $name
     * @param $url
     * @return void
     * @throws GitException
     */
    public function addRemote($name, $url)
    {
        $this->git->exec('remote-add', [$name, $url]);
    }

    /**
     * Delete the remote.
     *
     * @param $name
     * @return void
     */
    public function removeRemote($name)
    {
        $this->git->exec('remote-remove', [$name]);
    }

    /**
     * Rename the remote.
     *
     * @param $old
     * @param $new
     * @return void
     * @throws GitException
     */
    public function renameRemote($old, $new)
    {
        $this->git->exec('remote-rename', [$old, $new]);
    }

    /**
     * Check that a git user has been configured.
     *
     * @return bool
     */
    protected function isUserConfigured()
    {
        return isset($this->user);
    }

    /**
     * Process existing git configuration files. Files are read
     * from the system level to the local level, overwriting options
     * as we go.
     *
     * @return void
     */
    protected function processGitConfig()
    {
        $configs = [
            "/etc/gitconfig",
            realpath("~/.gitconfig"),
            "{$this->git->getProjectDirectory()}/.git/config",
        ];

        foreach ($configs as $index => $file) {
            if (!is_file($file)) {
                continue;
            }

            if ($index === 2) {
                $this->initialized = true;
            }

            foreach (parse_ini_file($file, true) as $section => $values) {
                if (substr($section, 0, 6) === 'remote') {
                    $remote = substr($section, 7);
                    $this->config['remotes'][] = [
                        'name' => $remote,
                        'url' => $values['url'],
                    ];
                } elseif (substr($section, 0, 6) === 'branch') {
                    $this->config['branches'][] = substr($section, 7);
                } elseif ($section === 'user') {
                    $this->config['user'] = $values;
                }
            }
        }
    }
}
