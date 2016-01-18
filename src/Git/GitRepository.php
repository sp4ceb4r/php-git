<?php

namespace Git;

use InvalidArgumentException;
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
     * @var string
     */
    protected $url;

    /**
     * @var bool
     */
    protected $initialized = false;

    /**
     * Initialize a new git repository.
     *
     * @param string $path
     * @param string $remote_url
     * @param \Shell\Output\ProcessOutputInterface $output
     * @return GitRepository
     * @throws GitException
     */
    public static function init($path, $remote_url = null, $output = null)
    {
        $fs = new Filesystem();
        if (!$fs->isAbsolutePath($path)) {
            throw new InvalidArgumentException('Path must be absolute.');
        }

        $fs->mkdir($path);

        $git = new Git($path);
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
     * @param string $path
     * @param string $remote_url
     * @param null $repo
     * @param \Shell\Output\ProcessOutputInterface $output
     * @param bool $background
     * @return GitRepository|Process
     * @throws GitException
     */
    public static function copy($path, $remote_url, &$repo = null, $output = null, $background = false)
    {
        $fs = new Filesystem();
        if (!$fs->isAbsolutePath($path)) {
            throw new InvalidArgumentException('Path must be absolute.');
        }

        $fs->mkdir($path);

        $git = new Git($path);
        $repo = new GitRepository($git, $output);

        $args = [
            $remote_url,
            $path,
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
     * @param $path
     * @param \Shell\Output\ProcessOutputInterface $output
     * @return GitRepository
     */
    public static function open($path, $output = null)
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException("[$path] not found.");
        }

        $config = realpath($path).'/.git/config';
        if (!is_file($config)) {
            throw new InvalidArgumentException("[$path] is not a git repo.");
        }

        return new GitRepository(new Git($path), $output);
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
     * Get the current project directory.
     *
     * @return string
     */
    public function getProjectDirectory()
    {
        return $this->git->getProjectDirectory();
    }

    /**
     * Set configuration option.
     *
     * @param $key
     * @param $value
     * @param string $reach
     * @return void
     */
    public function configure($key, $value, $reach = 'local')
    {
        $reach = strtolower($reach);
        if (!in_array($reach, [Git::LOCAL_CONFIG, Git::GLOBAL_CONFIG, Git::SYSTEM_CONFIG])) {
            throw new \LogicException("Invalid reach [$reach].");
        }

        $this->git->exec('config', [], [
            '--'.$reach,
            "$key" => $value,
        ]);
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

        $tmp = $this->output->readStdoutLines();
        $branch = end($tmp);
        unset($tmp);

        return $branch;
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

        $this->git->exec('branch', [], [$key => $branch]);

        if ($remote) {
            unset($process);
            $this->git->exec('push', ['origin', ":$branch"]);
        }
    }

    /**
     * Checkout the desired point in time.
     *
     * @param string $refspec
     * @param bool $createBranch
     * @param string|null $branchName
     * @return void
     */
    public function checkout($refspec, $createBranch = false, $branchName = null)
    {
        $args = [
            $refspec,
        ];

        $options = [];
        if ($createBranch) {
            if ($refspec === $branchName) {
                $args = [];
            }

            $options['-b'] = $branchName ?: $refspec;
        }

        $this->git->exec('checkout', $args, $options);
    }

    /**
     * List commits.
     *
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws GitException
     */
    public function listCommits($offset = 0, $limit = -1)
    {
        $options = [
            '--pretty=oneline' => null,
            '-n' => $limit,
        ];

        if ($limit < 0) {
            unset($options['-n']);
        }

        $this->git->exec('log', [], $options);

        return array_map(function($line) {
            list($sha, $description) = explode(' ', $line, 2);
            return [
                'sha' => $sha,
                'description' => $description,
            ];
        }, $this->output->readStdOutLines());
    }

    /**
     * @param $sha
     * @return bool
     */
    public function isCommmit($sha)
    {
        $options = [
            '--quiet' => null,
            '--verify' => "{$sha}^{commit}",
        ];

        try{
            $this->git->exec('rev-parse', [], $options);
        } catch (GitException $ex) {
            return false;
        }

        return true;
    }

    /**
     * Fetch changes from the remote.
     *
     * @param bool $tags
     * @return void
     * @throws GitException
     */
    public function fetch($tags = false)
    {
        $options = [];

        if ($tags) {
            $options['--tags'] = true;
        }

        $this->git->exec('fetch', [], $options);
    }

    /**
     * List available tags.
     *
     * @return array
     * @throws GitException
     */
    public function listTags()
    {
        $this->fetch(true);

        $this->git->exec('tag');

        return array_map('trim', $this->output->readStdOutLines());
    }

    /**
     * Pull (and apply) changes from remote.
     *
     * @return void
     * @throws GitException
     */
    public function pull()
    {
        $this->git->exec('pull');
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

        $this->config['remotes'][$name] = [
            'name' => $name,
            'url' => $url,
        ];
    }

    /**
     * List configured remotes.
     *
     * @param string $remote
     * @return array|null
     */
    public function listRemotes($remote = null)
    {
        if (is_null($remote)) {
            return array_values($this->config['remotes']);
        }

        if (isset($this->config['remotes'][$remote])) {
            return $this->config['remotes'][$remote];
        }
        return null;
    }

    /**
     * Delete the remote.
     *
     * @param $name
     * @return void
     */
    public function removeRemote($name)
    {
        try {
            $this->git->exec('remote-remove', [$name]);
            unset($this->config['remotes'][$name]);
        } catch (\Exception $ex) {
            // Suppress the exception.
            // Remote didn't exist?
        }
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

        $this->config['remotes'][$new] = $this->config['remotes'][$old];
        unset($this->config['remotes'][$old]);
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
                    if (!isset($this->config['remotes'])) {
                        $this->config['remotes'] = [];
                    }

                    $this->config['remotes'][$remote] = [
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
