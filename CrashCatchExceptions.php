<?php

class CrashCatchException extends Exception
{
    /**
     * CritiMonException constructor.
     * @param string $message
     * @param int $http_code
     */
    public function __construct($message = "", $http_code = 0)
    {
        parent::__construct($message, $http_code, null);
    }
}