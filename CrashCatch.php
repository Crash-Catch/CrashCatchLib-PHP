<?php

require_once 'CrashCatchBase.php';
require_once 'CrashCatchExceptions.php';

class CrashCatch extends CrashCatchBase
{
    public function __construct($project_id, $api_key, $version_number)
    {
        parent::__construct($project_id, $api_key, $version_number);
    }

    public function initialise()
    {
        $this->cookies = array();
        if (isset($_COOKIE["SESSIONID"]))
        {
            $this->cookies["SESSIONID"] = $_COOKIE["SESSIONID"];
        }

        try
        {
            if (empty($this->device_id))
            {
                if (isset($_COOKIE["device_id"]))
                {
                    $this->device_id = $_COOKIE["device_id"];
                    setcookie("device_id", $this->device_id, time() + (60 * 10), crashcatch_url);
                }
                else
                {
                    $this->device_id = $this->generateRandomString();
                    setcookie("device_id", $this->device_id, time() + (60 * 10), crashcatch_url);
                }
            }

            if (count($_COOKIE) > 0)
            {

                foreach ($_COOKIE as $key =>$value)
                {
                    $this->cookies[$key] = $value;
                }
            }
            $curl = $this->returnCurlClient(array("ProjectID" => $this->project_id, "DeviceID" => $this->device_id, "AppVersion" => $this->app_version), "initialise");

            $response = curl_exec($curl);
            $err = curl_error($curl);
            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($err)
            {
                if (function_exists("error_log"))
                {
                    error_log("Unable to initialise CrashCatch: Error: $err");
                }
            }
            else
            {
                if ($httpcode === 200)
                {
                    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
                    if ($this->cookies === null)
                    {
                        $this->cookies = array();
                    }


                    foreach ($matches[0] as $cookie)
                    {
                        //Remove the set-cookie header
                        if (stripos($cookie, "set-cookie:") === 0)
                        {
                            $cookie = str_ireplace("set-cookie:", "", $cookie);
                        }

                        //Now explode the cookie into key/value pair
                        $keyValueCookie = explode("=", $cookie);
                        //Need to keep the DO-LB cookie as this tells the load balancer to send the request to the same server
                        if (trim($keyValueCookie[0]) === "DO-LB")
                        {
                            $this->cookies[trim($keyValueCookie[0])] = trim($keyValueCookie[1]);
                            setcookie("DO-LB", trim($keyValueCookie[1]), time() + (60 * 10), crashcatch_url);
                        }
                        if (trim($keyValueCookie[0]) === "SESSIONID")
                        {
                            $this->cookies[trim($keyValueCookie[0])] = trim($keyValueCookie[1]);
                            setcookie("SESSIONID", trim($keyValueCookie[1]), time() + (60 * 10), crashcatch_url);
                        }
                        if (trim($keyValueCookie[0]) === "session_id")
                        {
                            $this->cookies[trim($keyValueCookie[0])] = trim($keyValueCookie[1]);
                            setcookie("session_id", trim($keyValueCookie[1]), time() + (60 * 10), crashcatch_url);
                        }
                    }
                    set_error_handler(array($this, "ErrorHandler"));
                    register_shutdown_function(array($this, "FatalErrorHandler"));
                    $this->initialised = true;
                    $body = substr($response, $header_size);
                    return json_decode($body);
                }
                else
                {
                    throw new CrashCatchException("CrashCatch initialisation failed: $response", $httpcode);
                }
            }
        }
        catch (Exception $ex)
        {
            if (function_exists("error_log"))
            {
                error_log("Unable to initialise Crash Catch. Error: " . $ex->getMessage());
            }
        }
    }

    public function FatalErrorHandler()
    {
        $error = error_get_last();
        if ($error !== NULL && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING,E_RECOVERABLE_ERROR))) {
            $postFields = array();
            $postFields["ExceptionType"] = "PHP_FATAL";
            $postFields["CrashType"] = "Unhandled";
            $postFields["Stacktrace"] = $error["message"];
            $postFields["ExceptionMessage"] = "N/A";
            $postFields["PHPFile"] = $error["file"];
            $postFields["LineNo"] = $error["line"];
            $postFields["Exception"] = $error["message"];
            $postFields["ErrorCode"] = -1;
            $postFields["PHPVersion"] = phpversion();
            $postFields["Platform"] = PHP_OS;
            $postFields["Uname"] = php_uname("s") . " " . php_uname("r") . " " . php_uname("v") . " " . php_uname("m");
            $postFields["Severity"] = CrashCatchSeverity::HIGH;
            $this->sendCrashData($postFields);
            $this->initialised = false;
            die;
        }
    }

    public function ErrorHandler($phpErrorType, $message, $filename, $lineno)
    {
            $postFields = array();
            $backtrace = debug_backtrace();
            $args = $backtrace[0]["args"];
            $exceptionType = "Unkown PHP Error Type";
            switch ($phpErrorType)
            {
                case E_WARNING:
                    $exceptionType = "PHP_RUNTIME_WARNING";
                    $severity = CrashCatchSeverity::LOW;
                    break;
                case E_PARSE:
                    $exceptionType = "PHP_RUNTIME_PARSE";
                    $severity = CrashCatchSeverity::LOW;
                    break;
                case E_NOTICE:
                    $exceptionType = "PHP_RUNTIME_NOTICE";
                    $severity = CrashCatchSeverity::LOW;
                    break;
                case E_STRICT:
                    $exceptionType = "PHP_STRICT";
                    $severity = CrashCatchSeverity::LOW;
                    break;
                case E_DEPRECATED:
                    $exceptionType = "PHP_RUNTIME_DEPRECATED";
                    $severity = CrashCatchSeverity::LOW;
                    break;
                case E_CORE_ERROR:
                    $exceptionType = "PHP_CORE_ERROR";
                    $severity = CrashCatchSeverity::HIGH;
                    break;
                case E_CORE_WARNING:
                    $exceptionType = "PHP_CORE_WARNING";
                    $severity = CrashCatchSeverity::MEDIUM;
                    break;
                case E_COMPILE_ERROR:
                    $exceptionType = "PHP_COMPILE_ERROR";
                    $severity = CrashCatchSeverity::HIGH;
                    break;
                case E_COMPILE_WARNING:
                    $exceptionType = "PHP_COMPILE_WARNING";
                    $severity = CrashCatchSeverity::HIGH;
                    break;

                case E_USER_ERROR:
                    $exceptionType = "PHP_ERROR";
                    $severity = CrashCatchSeverity::HIGH;
                    break;
                case E_USER_WARNING:
                    $exceptionType = "PHP_USER_WARNING";
                    $severity = CrashCatchSeverity::MEDIUM;
                    break;
                case E_USER_DEPRECATED:
                    $exceptionType = "PHP_USER_DEPRECATED";
                    $severity = CrashCatchSeverity::LOW;
                    break;
                case E_USER_NOTICE:
                    $exceptionType = "PHP_USER_NOTICE";
                    $severity = CrashCatchSeverity::LOW;
                    break;
                default:
                    $exceptionType = "PHP_UNKNOWN ($phpErrorType)";
                    $severity = CrashCatchSeverity::MEDIUM;
                    break;

            }
            $postFields["ExceptionType"] = $exceptionType;
            $postFields["CrashType"] = "Unhandled";
            $postFields["ExceptionMessage"] = $message;
            $postFields["Stacktrace"] = $args[1];
            if (isset($backtrace[0]["file"]))
            {
                $postFields["PHPFile"] = $backtrace[0]["file"];
            }
            else
            {
                $postFields["PHPFile"] = "N/A";
            }
            if (isset($backtrace[0]["line"]))
            {
                $postFields["LineNo"] = $backtrace[0]["line"];
            }
            else
            {
                $postFields["LineNo"] = 0;
            }
            $postFields["Exception"] = $args[1];
            $postFields["ErrorCode"] = $phpErrorType;
            $postFields["PHPVersion"] = phpversion();
            $postFields["Platform"] = PHP_OS;
            $postFields["Uname"] = php_uname("s") . " " . php_uname("r") . " " . php_uname("v") . " " . php_uname("m");
            $postFields["Severity"] = $severity;
            $this->sendCrashData($postFields);
            $this->initialised = false;
            return true;

    }

    /**
     * @param Exception $exception
     * @param string $severity
     * @param null $customProperties
     * @throws CrashCatchException
     */
    public function reportCrash($exception, $severity = CrashCatchSeverity::LOW, $customProperties = null)
    {
        if (!is_subclass_of($exception, "Exception") && !is_a($exception, "Exception"))
        {
            throw new CrashCatchException("The exception object is not a sub class of Exception");
        }

        $postFields = array();
        $postFields["ExceptionType"] = get_class($exception);
        $postFields["CrashType"] = "Handled";
        $postFields["ExceptionMessage"] = $exception->getMessage();
        $postFields["Stacktrace"] = $exception->getTraceAsString();
        $postFields["PHPFile"] = $exception->getFile();
        $postFields["LineNo"] = $exception->getLine();
        $postFields["Exception"] = $exception->getMessage();
        $postFields["ErrorCode"] = $exception->getCode();
        $postFields["PHPVersion"] = phpversion();
        $postFields["Platform"] = PHP_OS;
        $postFields["Uname"] = php_uname("s") . " " . php_uname("r") . " " . php_uname("v") . " " . php_uname("m");
        $postFields["Severity"] = $severity;
        $postFields["CustomProperty"] = $customProperties;
        $this->sendCrashData($postFields);
    }

    /**
     * @param $postFields
     * @throws CrashCatchException
     */
    private function sendCrashData($postFields)
    {

        if (!$this->initialised)
        {
            $this->initialise();
        }

        try
        {
            $postFields["ProjectID"] = $this->project_id;
            $postFields["DeviceID"] = $this->device_id;
            $postFields["VersionName"] = $this->app_version;
            $postFields["DeviceType"] = "PHP";
            $postFields["Locale"] = Locale::getDefault();

            $curl = $this->returnCurlClient($postFields, "crash");


            $response = curl_exec($curl);
            $err = curl_error($curl);

            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($err)
            {
                throw new CrashCatchException("Unable to initialise CrashCatch. Error: " . $err);
            }
            else
            {
                if ($httpcode === 200)
                {
                    //Reset the cookie to expire in 10 minutes from now
                    setcookie("device_id", $this->device_id, time() + (60 * 10), crashcatch_url);
                    $headersAndBody = explode("\r\n\r\n", $response);
                    if (isset($headersAndBody[1]))
                    {
                        if (strlen($headersAndBody[1]) > 0)
                        {
                            $jsonData = json_decode($response);
                            if ($jsonData->result === 5)
                            {
                                $this->initialise();
                            }
                        }
                    }
                }
                else
                {
                    throw new CrashCatchException("Failed to send crash: $response", $httpcode);
                }
            }
        }
        catch (Exception $ex)
        {
            if (function_exists("error_log"))
            {
                error_log("CrashCatch: Failed to send crash. Error: " . $ex->getMessage());
            }
        }
    }

}

abstract class CrashCatchSeverity
{
    const LOW = "Low";
    const MEDIUM = "Medium";
    const HIGH = "High";
}
