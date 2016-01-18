<?php

use Git\GitRepository;


/**
 * Class GitRepositoryTest
 */
class GitRepositoryTest extends PHPUnit_Framework_TestCase
{
    public function test_init()
    {
        $repo = GitRepository::init('/Users/jacob/dev/research/tmp', 'git@github.com:sp4ceb4r/php-git.git');
        $this->assertNotNull($repo);
    }

    public function test_copy()
    {
        $repo = GitRepository::copy('/Users/jacob/dev/research/tmp', 'git@github.com:sp4ceb4r/php-git.git');
        $this->assertNotNull($repo);
    }

    public function test_open()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'));
        $this->assertNotNull($repo);
    }

    public function test_listRemotes()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'));
        $remotes = $repo->listRemotes();

        $expected = [
            [
                'name' => 'origin',
                'url' => 'git@github.com:sp4ceb4r/php-git.git'
            ],
        ];

        $this->assertEquals($expected, $remotes);
    }

    public function test_listRemotes_filters()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'));
        $remotes = $repo->listRemotes('origin');

        $expected = [
            'name' => 'origin',
            'url' => 'git@github.com:sp4ceb4r/php-git.git'
        ];

        $this->assertEquals($expected, $remotes);
    }

    /**
     * @dependsOn test_listRemotes
     */
    public function test_addRemote()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'));

        $repo->addRemote('copy', 'git@github.com:sp4ceb4r/php-git.git');

        $this->assertEquals([
            'name' => 'copy',
            'url' => 'git@github.com:sp4ceb4r/php-git.git',
        ], $repo->listRemotes('copy'));
    }

    /**
     * @dependsOn test_listRemotes
     */
    public function test_removeRemote()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'));

        $repo->removeRemote('copy');

        $this->assertNull($repo->listRemotes('copy'));
    }

    /**
     * @dependsOn test_removeRemote
     */
    public function test_removeRemote_notfound()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'), new \Shell\Output\OutputHandler());
        $repo->removeRemote('copy');
        $this->assertNull($repo->listRemotes('copy'));
    }

    /**
     * @dependsOn test_addRemote
     */
    public function test_renameRemote()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'), new \Shell\Output\OutputHandler());
        $repo->addRemote('abc', 'git@github.com:sp4ceb4r/php-git');
        $repo->renameRemote('abc', 'def');

        $this->assertNotNull($repo->listRemotes('def'));
        $repo->removeRemote('def');
    }

    /**
     * @dependsOn test_addRemote
     * @expectedException \Git\GitException
     */
    public function test_renameRemote_conflict()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'), new \Shell\Output\OutputHandler());
        $repo->addRemote('abc', 'git@github.com:sp4ceb4r/php-git');
        $repo->addRemote('def', 'git@github.com:sp4ceb4r/php-git');

        try {
            $repo->renameRemote('def', 'abc');
        } finally {
            $repo->removeRemote('abc');
            $repo->removeRemote('def');
        }
    }

    public function test_listBranches()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'), new \Shell\Output\OutputHandler());
        $branches = $repo->listBranches();
        $this->assertTrue(in_array('master', $branches));
    }

    /**
     * @dependsOn test_listBranches
     */
    public function test_checkout_createBranch()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'), new \Shell\Output\OutputHandler());

        $repo->checkout('testbranch', true);
        $branches = $repo->listBranches();

        $this->assertTrue(in_array('testbranch', $branches));

        $repo->checkout('master');
        $repo->deleteBranch('testbranch');
    }

    public function test_currentBranch()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'), new \Shell\Output\OutputHandler());
        $repo->checkout('testbranch', true);

        $this->assertEquals('testbranch', $repo->currentBranch());

        $repo->checkout('master');
        $repo->deleteBranch('testbranch');
    }

    public function test_listTags()
    {
        $repo = GitRepository::open(realpath(__DIR__.'/../'), new \Shell\Output\OutputHandler());
        $tags = $repo->listTags();

        $this->assertTrue(in_array('testtag', $tags));
    }
}