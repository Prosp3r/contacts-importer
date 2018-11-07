# Contacts Importer

## Installation

To install, use composer:
```
composer require cang-ha/contacts-importer
```

## Usage
```
$clientID = 'your_client_id';
$clientSecret = 'your_client_secret';
$redirectUri = 'your_redirect_url';

$googleImporter = new GoogleImporter($clientID, $clientSecret, $redirectUri);

try {
    $googleImporter->processCallback();
    $contactsGoogle = $googleImporter->getContacts();

    //store token for later use
    $tokenGoogle = [
        'accessToken' => $googleImporter->getAccessToken(),
        'refreshToken' => $googleImporter->getRefreshToken(),
        'expires' => $googleImporter->getExpires()
    ];
    $_SESSION['stored_google_token'] = serialize($tokenGoogle);
    
    // display the contacts
    foreach ($contactsGoogle as $contact) {
        echo ('Full name: '. $contact->getFullName());
        echo ('<br>First name: '. $contact->getFirstName());
        echo ('<br>Last name: '. $contact->getLastName());
        echo ('<br>Email: '. $contact->getEmail());
    }

} catch (\ContactImporter\Exception\OAuth2\OAuth2InvalidAuthCodeException $e) {
    // handle case where use deny the oauth2 request

} catch (\GuzzleHttp\Exception\GuzzleException $e) {
    // handle restful api request error
}
    
```