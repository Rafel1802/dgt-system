<?php

namespace App\Exceptions;

use RuntimeException;

class GoogleSheetsNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Google Sheets export isn\'t configured yet — ask an admin to set GOOGLE_SERVICE_ACCOUNT_PATH.');
    }
}
