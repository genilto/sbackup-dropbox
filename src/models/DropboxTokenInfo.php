<?php
namespace genilto\sbackup\adapters\models;

use \Kunnu\Dropbox\Models\AccessToken;

class DropboxTokenInfo
{
    /**
     * Access Token
     *
     * @var AccessToken
     */
    protected $accessToken;

    /**
     * Time when created
     *
     * @var int
     */
    protected $creationTime;

    /**
     * Team ID
     *
     * @var int
     */
    protected $expirationTime;

    /**
     * Create a new AccessToken instance
     *
     * @param array $data
     */
    public function __construct(AccessToken $accessToken)
    {
        $this->accessToken = $accessToken;
        $this->creationTime = time();

        // Calculate the expiration time (Subtract 1 hour from the time)
        $this->expirationTime = ($this->creationTime + $accessToken->getExpiryTime()) - 3600;
    }

    /**
     * Get Access Token
     *
     * @return AccessToken
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Get the creation time
     *
     * @return int
     */
    public function getCreationTime()
    {
        return $this->creationTime;
    }

    /**
     * Get the expiration time
     *
     * @return int
     */
    public function getExpirationTime()
    {
        return $this->expirationTime;
    }

    /**
     * Verify if token is expired
     *
     * @return boolean
     */
    public function isTokenExpired()
    {
        return ($this->expirationTime <= time());
    }
}
