<?php
namespace ContactImporter\Exception\OAuth2;

use ContactImporter\Exception\ContactImporterException;

class OAuth2InvalidAuthCodeException extends \RuntimeException implements ContactImporterException {}
