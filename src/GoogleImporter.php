<?php
namespace ContactImporter;

use ContactImporter\Exception\OAuth2\OAuth2AuthorizeException;
use ContactImporter\Exception\OAuth2\OAuth2InvalidAuthCodeException;
use ContactImporter\Exception\OAuth2\OAuth2InvalidRefreshTokenException;
use ContactImporter\Exception\OAuth2\OAuth2InvalidStateException;
use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Grant\RefreshToken as RefreshTokenGrant;
use League\OAuth2\Client\Token\AccessToken;

class GoogleImporter extends OAuth2ContactImporter
{
    const CONTACT_URI = 'https://www.google.com/m8/feeds/contacts/default/full?alt=json&max-results=200&v=3.0';
    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUri
     * @return AbstractProvider
     */
    function auth($clientId, $clientSecret, $redirectUri)
    {
        return new Google([
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri'  => $redirectUri,
            'accessType' => 'offline',
            'scope' => 'https://www.googleapis.com/auth/contacts.readonly'
        ]);


    }

    /**
     * get the standard OAuth2 authorization url for authorization code grant
     * @return string
     */
    function getAuthorizationUrl()
    {
        return  $this->provider->getAuthorizationUrl([
            'scope' => [
                'https://www.googleapis.com/auth/contacts.readonly',
            ]
        ]);
    }

    /**
     * @return GenericContact[];
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function getContacts()
    {
        $client = new Client();

        $options = ['headers' => [
            'Authorization' => "Bearer {$this->accessToken}",
            'Accept' => 'application/json',
            'content-type' => 'application/json'
        ]];
        try {
            $response = $client->request('GET', self::CONTACT_URI, $options);
            $rawContacts = json_decode($response->getBody()->getContents(), true);
            $contacts = [];
            if (!empty($rawContacts['feed']['entry'])) {
                foreach ($rawContacts['feed']['entry'] as $rawContact) {
                    if (isset($rawContact['gd$email'])) {
                        $contacts[] =  new GenericContact( $rawContact['title']['$t'], $rawContact['gd$email'][0]['address']);
                    }
                }
            }

            return $contacts;
        }
        catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw $e;
        }

    }

    /**
     * this function should set the access token into $this->accessToken
     */
    function processCallback()
    {
        if (isset($_SESSION['GoogleImporterToken'])) {
            /**
             * @var AccessToken $token
             */
            $token = unserialize($_SESSION['GoogleImporterToken']);
            $this->accessToken = $token->getToken();
            $this->refreshToken = $token->getRefreshToken();
            $this->expires = $token->getExpires();
            unset($_SESSION['GoogleImporterToken']);
        }
        elseif (empty($this->accessToken)) {
            if (!empty($_GET['error'])) {
                // Got an error, probably user denied access
                throw new OAuth2AuthorizeException();
            } elseif (empty($_GET['code'])) {
                // Don't have an authorization code
                $_SESSION['oauth2state'] = $this->provider->getState();
                throw new OAuth2InvalidAuthCodeException();
            } elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && ($_GET['state'] !== $_SESSION['oauth2state']))) {
                // State is invalid, possible CSRF attack in progress
                unset($_SESSION['oauth2state']);
                throw new OAuth2InvalidStateException();
            } else {
                // Try to get an access token (using the authorization code grant)
                $token = $this->provider->getAccessToken('authorization_code', [
                    'code' => $_GET['code']
                ]);

                $_SESSION['GoogleImporterToken'] = serialize($token);
                header("Location: {$this->redirectUri}");
            }
        }
    }

    /**
     * this function should set the access token into $this->accessToken and return it
     * @return string
     */
    function refreshToken()
    {
        if (empty($this->refreshToken)) {
            throw new OAuth2InvalidRefreshTokenException();
        }

        $grant = new RefreshTokenGrant();
        $token = $this->provider->getAccessToken($grant, ['refresh_token' => $this->refreshToken]);

        $this->accessToken = $token->getToken();
        $this->refreshToken = $token->getRefreshToken();
        $this->expires = $token->getExpires();
    }

    function getAccessToken()
    {
        $now = time() + 300;
        if ($this->expires <= $now) {
            $this->refreshToken();
        }

        return $this->accessToken;
    }

    /**
     * @return string
     */
    function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * @return timestamp
     */
    function getExpires()
    {
        return $this->expires;
    }
}