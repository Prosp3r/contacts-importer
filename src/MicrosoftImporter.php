<?php
namespace ContactImporter;

use \ContactImporter\Exception\OAuth2\OAuth2AuthorizeException;
use \ContactImporter\Exception\OAuth2\OAuth2InvalidAuthCodeException;
use \ContactImporter\Exception\OAuth2\OAuth2InvalidRefreshTokenException;
use \ContactImporter\Exception\OAuth2\OAuth2InvalidStateException;
use \GuzzleHttp\Client;
use \League\OAuth2\Client\Provider\AbstractProvider;
use \League\OAuth2\Client\Grant\RefreshToken as RefreshTokenGrant;
use League\OAuth2\Client\Provider\GenericProvider;
use \League\OAuth2\Client\Token\AccessToken;
use Microsoft\Graph\Exception\GraphException;
use \Microsoft\Graph\Graph;
use \Microsoft\Graph\Model;


class MicrosoftImporter extends OAuth2ContactImporter
{
    const CONTACT_URI = 'https://www.google.com/m8/feeds/contacts/default/full?alt=json&max-results=200&v=3.0';
    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUrl
     * @return AbstractProvider
     */
    function auth($clientId, $clientSecret, $redirectUrl)
    {
        return new GenericProvider([
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri'  => $redirectUrl,
            'urlAuthorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'urlAccessToken' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => '',
            'scopes' => 'openid profile offline_access User.Read Contacts.Read'
        ]);


    }

    /**
     * get the standard OAuth2 authorization url for authorization code grant
     * @return string
     */
    function getAuthorizationUrl()
    {
        return  $this->provider->getAuthorizationUrl([
            'state' => 'FROM_MICROSOFT',
        ]);
    }

    /**
     * @return GenericContact[];
     * @throws GraphException
     */
    function getContacts()
    {
        $graph = new Graph();
        $graph->setAccessToken($this->getAccessToken());
        try {

            $user = $graph->createRequest('GET', '/me')
                ->setReturnType(Model\User::class)
                ->execute();

            $contactsQueryParams = array (
                // // Only return givenName, surname, and emailAddresses fields
                "\$select" => "givenName,surname,emailAddresses",
                // Sort by given name
                "\$orderby" => "givenName ASC",
                // Return at most 200 results
                "\$top" => "200"
            );

            $getContactsUrl = '/me/contacts?'.http_build_query($contactsQueryParams);
            $rawContacts = $graph->createRequest('GET', $getContactsUrl)
                ->setReturnType(Model\Contact::class)
                ->execute();

            $contacts = [];
            if (!empty($rawContacts)) {
                foreach ($rawContacts as $rawContact) {
                    if (isset($rawContact->getEmailAddresses()[0]['address'])) {
                        $email = $rawContact->getEmailAddresses()[0]['address'];
                        $firstName = $rawContact->getGivenName();
                        $lastName = $rawContact->getSurname();
                        if(!empty($firstName) && !empty($lastName)) {
                            $fullName = "{$rawContact->getGivenName()} {$rawContact->getSurname()}";
                        }
                        elseif (!empty($firstName)) {
                            $fullName = $firstName;
                        }
                        elseif (!empty($lastName)) {
                            $fullName = $lastName;
                        }
                        else {
                            $fullName = $email;
                        }

                        $contacts[] = new GenericContact($fullName, $email, $firstName, $lastName);
                    }

                }

            }

            return $contacts;

        }
        catch (GraphException $e) {
            throw $e;
        }

    }

    /**
     * this function should set the access token into $this->accessToken
     */
    function processCallback()
    {
        if (isset($_SESSION['MicrosoftImporterToken'])) {
            /**
             * @var AccessToken $token
             */
            $token = unserialize($_SESSION['MicrosoftImporterToken']);
            $this->accessToken = $token->getToken();
            $this->refreshToken = $token->getRefreshToken();
            $this->expires = $token->getExpires();
            unset($_SESSION['MicrosoftImporterToken']);
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

                $_SESSION['MicrosoftImporterToken'] = serialize($token);
                header("Location: {$this->redirectUri}?from=Microsoft");
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