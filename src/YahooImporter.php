<?php
namespace ContactImporter;

use ContactImporter\Exception\OAuth2\OAuth2AuthorizeException;
use ContactImporter\Exception\OAuth2\OAuth2InvalidAuthCodeException;
use ContactImporter\Exception\OAuth2\OAuth2InvalidRefreshTokenException;
use ContactImporter\Exception\OAuth2\OAuth2InvalidStateException;
use GuzzleHttp\Client;
use Hayageek\OAuth2\Client\Provider\Yahoo;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Grant\RefreshToken as RefreshTokenGrant;
use League\OAuth2\Client\Token\AccessToken;

class YahooImporter extends OAuth2ContactImporter
{
    const CONTACT_URI = 'https://social.yahooapis.com/v1/user/me/contacts;out=name,email?format=json';
    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUrl
     * @return AbstractProvider
     */
    function auth($clientId, $clientSecret, $redirectUrl)
    {
        return new Yahoo([
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri'  => $redirectUrl,
        ]);

    }

    /**
     * get the standard OAuth2 authorization url for authorization code grant
     * @return string
     */
    function getAuthorizationUrl()
    {
        return  $this->provider->getAuthorizationUrl();
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
            if (isset($rawContacts['contacts']) && isset($rawContacts['contacts']['contact'])) {
                foreach ($rawContacts['contacts']['contact'] as $rawContact) {

                    if (!empty($rawContact['fields'])) {
                        $tempContact = [];
                        foreach ($rawContact['fields'] as $field) {
                            $tempContact[$field['type']] = $field['value'];
                        }

                        if (isset($tempContact['email'])) {
                            if (isset($tempContact['name'])) {
                                $fullName = "{$tempContact['name']['givenName']} {$tempContact['name']['middleName']} {$tempContact['name']['familyName']}";
                                $contact = new GenericContact($fullName, $tempContact['email'], $tempContact['name']['givenName'], $tempContact['name']['familyName']);
                            } else {
                                $contact = new GenericContact($tempContact['email'], $tempContact['email']);
                            }
                            $contacts[] = $contact;
                        }
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
        if (isset($_SESSION['YahooImporterToken'])) {
            /**
             * @var AccessToken $token
             */
            $token = unserialize($_SESSION['YahooImporterToken']);

            $this->accessToken = $token->getToken();
            $this->refreshToken = $token->getRefreshToken();
            $this->expires = $token->getExpires();

            unset($_SESSION['YahooImporterToken']);
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

                $_SESSION['YahooImporterToken'] = serialize($token);
            }
        }
        header("Location: {$this->redirectUri}");
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