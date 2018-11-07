<?php
namespace ContactImporter\Exception\OAuth2;

use ContactImporter\Exception\ContactImporterException;

class OAuth2InvalidRefreshTokenException extends \RuntimeException implements ContactImporterException {}
