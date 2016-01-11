<?php

namespace Git;

use InvalidArgumentException;
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
     * Initialize a new git repository.
     * @param string $project_dir
     * @param string $remote_url
     * @return GitRepository
     * @throws GitException
     */
    public static function init($project_dir, $remote_url = null)
    {
        $fs = new Filesystem();
        $fs->mkdir($project_dir);

        $git = new Git($project_dir);
        $git->exec('init');

        $repo = new GitRepository($git);

        if (!is_null($remote_url)) {
            $repo->addRemote('origin', $remote_url);
            $repo->setUpstream();

            $repo->config['remotes'][] = [
                'url' => $remote_url,
                'name' => 'origin',
            ];
        }

        return $repo;
    }

    /**
     * Clone a repository (clone is a reserved keyword).
     *
     * @param string $project_dir
     * @param string $remote_url
     * @return GitRepository
     */
    public static function copy($project_dir, $remote_url)
    {
        $fs = new Filesystem();
        $fs->mkdir($project_dir);

        $git = new Git($project_dir);
        $repo = new GitRepository($git);

        $git->exec('clone', [$remote_url, $project_dir]);

        $repo->processGitConfig();
        $repo->setUpstream();

        return $repo;
    }

    /**
     * Open existing repository.
     *
     * @param $project_dir
     * @return GitRepository
     */
    public static function open($project_dir)
    {
        if (!is_dir($project_dir)) {
            throw new InvalidArgumentException("[$project_dir] not found.");
        }

        $config = "$project_dir/.git/config";
        if (!is_file($config)) {
            throw new InvalidArgumentException("[$project_dir] is not a git repo.");
        }

        return new GitRepository(new Git($project_dir));
    }

    /**
     * GitRepository constructor.
     *
     * @param Git $git
     */
    public function __construct(Git $git)
    {
        $this->git = $git;
        $this->processGitConfig();
    }

    public function listBranches()
    {
        $process = $this->git->exec('branch');

        $branches = [];
        foreach (explode("\n", $process->readStdOut()) as $line) {
            array_push($branches, trim(trim($line, '*')));
        }

        return $branches;
    }

    public function currentBranch()
    {
        $process = $this->git->exec('rev-parse', [], ['--abbrev-ref' => 'HEAD']);

        return $process->readStdOut();
    }

    public function deleteBranch($branch, $force = true, $remote = false)
    {
        $key = '-D';
        if (!$force) {
            $key = '-d';
        }

        $process = $this->git->exec('branch', [], [$key => $branch]);
        $stdout = $process->readStdOut();

        if ($remote) {
            unset($process);
            $process = $this->git->exec('push', ['origin', ":$branch"]);

            $stdout .= $process->readStdOut();
        }

        return $stdout;
    }

    public function configure($key, $value)
    {
    }

    public function clean()
    {
    }

    public function checkout($refspec, $createBranch = false)
    {
    }

    public function fetch()
    {
    }

    public function pull()
    {
    }

    public function setUpstream($remote = 'origin', $branch = null)
    {
        $args = [$remote];
        if (!is_null($branch)) {
            array_push($args, $branch);
        }

        $options = ['-u'];

        $this->git->exec('push', $args, $options);
    }

    public function stash()
    {
        if (!$this->isUserConfigured()) {
            throw new GitConfigException('No user defined.');
        }

        $this->git->exec('stash');
    }

    public function pop()
    {
        $this->git->exec('stash-pop');
    }

    public function listRemotes($verbose = false)
    {
        $options = [];
        if ($verbose) {
            $options['-v'] = null;
        }

        $this->git->exec('remote', [], $options);
    }

    public function addRemote($name, $url)
    {
        $this->git->exec('remote-add', [$name, $url]);
    }

    /**
     * @param $name
     */
    public function removeRemote($name)
    {
        $this->git->exec('remote-remove', [$name]);
    }

    public function renameRemote($old, $new)
    {
        $this->git->exec('remote-rename', [$old, $new]);
    }

    /**
     * @return bool
     */
    protected function isUserConfigured()
    {
        return isset($this->user);
    }

    protected function processGitConfig()
    {
        $configs = [
            "/etc/gitconfig",
            realpath("~/.gitconfig"),
            "{$this->git->getDir()}/.git/config",
        ];

        foreach ($configs as $file) {
            if (!is_file($file)) {
                continue;
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
