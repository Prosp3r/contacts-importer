<?php
namespace ContactImporter\Exception\OAuth2;

use ContactImporter\Exception\ContactImporterException;

class OAuth2InvalidStateException extends \RuntimeException implements ContactImporterException {}
