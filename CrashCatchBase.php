<?php

//define("crashcatch_url", "https://engine.crashcatch.com");
define("crashcatch_url", "http://192.168.1.47:5000/api");

class CrashCatchBase
{
    protected $project_id;
    protected $api_key;
    protected $project_version;
    protected $device_id;
    protected $cookies;
    protected $initialised = false;

    /**
     * CrashCatchBase constructor.
     * @param $project_id
     * @param $api_key
     * @param $version_number
     */
    public function __construct($project_id, $api_key, $version_number)
    {
        $this->project_id = $project_id;
        $this->api_key = $api_key;
        $this->project_version = $version_number;
    }

    /**
     * @param $postFields
     * @param $method
     * @return false|resource
     */
    protected function returnCurlClient($postFields, $method)
    {
        $curl = curl_init();

        $url = crashcatch_url . "/" . $method;

        $headers = array();
        $headers[] = "authorisation-token: " . $this->api_key;
        $headers[] = "content-type: application/json";
        $headers[] = "user-agent: CrashCatch PHP Library";
        if (($this->cookies !== null) && count($this->cookies) > 0)
        {
            $cookieString = "Cookie: ";
            foreach ($this->cookies as $key => $value)
            {
                $cookieString .= "$key=$value; ";
            }
            $cookieString = trim($cookieString);
            $headers[] = $cookieString;
            curl_setopt($curl, CURLOPT_COOKIE, $cookieString);
        }
        else
        {
            //If there are cookies set within PHP itself we'll add that as a header here
            $cookieString = "Cookie: ";
            foreach ($_COOKIE as $key=>$value)
            {
                $cookieString .= "$key=$value; ";
            }
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_HEADER => 1,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postFields),
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => $headers
        ));
        return $curl;
    }

    private function urlEncodePostArray($postFields)
    {
        $encodedPostFields = "";
        foreach ($postFields as $key => $value)
        {
            if ($key === "CustomProperty")
            {
                $encodedPostFields .= "$key=" . json_encode($value) ."&";
            }
            else
            {
                $encodedPostFields .= "$key=$value&";
            }
        }
        //Remove the end & and return
        return $encodedPostFields.substr(0, strlen($encodedPostFields)-1);
    }

    protected function generateRandomString() {
        $length = 10;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
