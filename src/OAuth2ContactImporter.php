<?php
namespace ContactImporter;

abstract class OAuth2ContactImporter implements ContactImporterInterface
{
    /**
     * @var \League\OAuth2\Client\Provider\AbstractProvider
     */
    protected $provider;

    /**
     * @var string
     */
    protected $redirectUri;

    /**
     * @var string
     */
    protected $accessToken;

    /**
     * @var string
     */
    protected $refreshToken;

    /**
     * @var int
     */
    protected $expires;

    /**
     * @var GenericContact[]
     */
    protected $contactList = [];

    /**
     * ContactImporter constructor.
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUri
     */
    public function __construct($clientId, $clientSecret, $redirectUri)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->provider = $this->auth($clientId, $clientSecret, $redirectUri);
        $this->redirectUri = $redirectUri;
    }

    /**
     * Use this function to set the stored access token to avoid requesting another access token
     *
     * @param string $accessToken
     * @param string $refreshToken
     * @param timestamp $expires
     */
    public function setToken($accessToken, $refreshToken, $expires) {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->expires = $expires;
    }

    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUrl
     * @return \League\OAuth2\Client\Provider\AbstractProvider
     */
    abstract function auth($clientId, $clientSecret, $redirectUrl);

    /**
     * get the standard OAuth2 authorization url for authorization code grant
     * @return string
     */
    abstract function getAuthorizationUrl();

    /**
     * Get the list of contacts via an array of GenericContact object
     * @return GenericContact[];
     */
    abstract function getContacts();

    /**
     * this function should set the access token into $this->accessToken
     */
    abstract function processCallback();

    /**
     * this function should set the access token into $this->accessToken and return it
     * @return string
     */
    abstract function refreshToken();

    /**
     * @return string
     */
    abstract function getAccessToken();

    /**
     * @return string
     */
    abstract function getRefreshToken();

    /**
     * @return timestamp
     */
    abstract function getExpires();

}
