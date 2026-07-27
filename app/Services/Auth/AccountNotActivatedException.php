<?php

namespace App\Services\Auth;

use Exception;

class AccountNotActivatedException extends Exception
{
    protected $message = 'Your account is not yet activated. Please contact the HR Department.';
}
