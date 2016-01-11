<?php

use Git\GitRepository;


/**
 * Class GitRepositoryTest
 */
class GitRepositoryTest extends PHPUnit_Framework_TestCase
{
    public function test_init()
    {
        $repo = GitRepository::init('/Users/jacob/dev/research/tmp', 'git@github.com:sp4ceb4r/helpsocial.git');
        $this->assertNotNull($repo);
    }

    public function test_copy()
    {
        $repo = GitRepository::copy('/Users/jacob/dev/research/tmp', 'git@github.com:sp4ceb4r/helpsocial.git');
        $this->assertNotNull($repo);
    }

    public function test_open()
    {
        $repo = GitRepository::open('/Users/jacob/dev/tweetmon');
        $this->assertNotNull($repo);
    }
}