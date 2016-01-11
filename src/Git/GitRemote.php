<?php

namespace Git;

use InvalidArgumentException;


/**
 * Class GitRemote
 */
class GitRemote
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $url;

    /**
     * GitRemote constructor.
     *
     * @param string $name
     * @param string $url
     */
    public function __construct($name, $url)
    {
        if (!is_string($name) || !is_string($url)) {
            throw new InvalidArgumentException('[name] and [url] must be strings.');
        }

        $this->name = $name;
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }
}