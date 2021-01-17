<img src="https://crashcatch.com/images/logo.png" width="150">


# Introduction
The CrashCatch PHP Library allows you to send both handled and unhandled crashes to the CrashCatch
Crash Monitoring Service (https://crashcatch.com).

# Prerequisites
There are a couple of dependencies in order to use the PHP CrashCatch library which are listed
below.
* PHP JSON
* CURL
* PHP-INTL

# Installing
When you download the library from GitHub you should have a folder that contains 3 files. 
Copy this folder where your project can reference it, it might be best to install it 
within your PHP shared library folder and include the CrashCatchLib-PHP folder in your php.ini
include path. 

# Using the Library
You need to include one file from the library, which is CrashCatch.php, you do not need to manually
include the other two files as these will get automatically included by CrashCatch.php. 

Before you can send any crashes, you need to create an instance of the CrashCatch class
and then call the initialisaton method. You also need to ensure that CrashCatch is initialised
on every page where you want it to be able to send errors. Therefore a good place to create
and initialise CrashCatch, it is a good idea to add the code to a PHP file which is included
on every page of your project. The CrashCatch initialisation should be wrapped in a try/catch
block as it can throw an exception if there's an issue. An issue might occur if you specify
the wrong API or App ID or if we are experiencing an issue with the platform. 

An example of creating an instance of CrashCatch and initialising CrashCatch is below. 

```
require_once 'CrashCatch.php';

$crashcatch = new CrashCatch(<your_api_key>, <your_app_id, <app_version>);
$crashcatch->initialise();
``` 

Your API key can be found in the settings of your account, there is a button next to the API
key to allow you to copy the API key to your clipboard. 

Your App ID can be found in the applications page and is an 8 digit number, and again, there is a 
button next to it allow copying the app id to your clipboard. 

If all you're interested in receiving are unhandled errors, such as PHP warnings or notices
then the above is all you need. However, you can also send handled exceptions. 

This is done using the reference to your crashcatch object you created above and calling the 
`reportCrash` method. An example for sending a handled exception is as follows:
```
try
{
    throw new Exception("Something has gone wrong");
}
catch (Exception $ex)
{
    $crashcatch->reportCrash($ex, CrashCatchSeverity::MEDIUM);
    //Or you can send custom parameters for extra debug purposes
    $crashcatch->reportCrash($ex, CrashCatchSeverity::Medium, array("my_key" => "my_value"));
}
```

The custom properties that are being passed to the second reportCrash method call will become a
json object that is passed to crashcatch, so you can create it as an object or an array
or a mixture if you need to send a complex json object with the crash. 

The following values are supported to be used for ``CrashCatchSeverity::``
* LOW
* MEDIUM
* HIGH

If you send anything other than the above, you will get an error response
back from crashcatch. 

# General Notes
PHP is a sequential language, i.e. it executes one line after another line, or one method
after another method, there's no such thing as threads to perform asynchronous functions. 

Therefore to ensure your project is not impacted by sending data to crashcatch in the event something
on our backend system isn't working the PHP Timeout is 1 second. This means that your project may
not load straight away if there's a connectivity problem with the crashcatch API the page load will
only be delayed by a maximum of 1 second. However, this 
happening will be hopefully unlikely. We have continuous monitoring on our backend systems and is 
handled through a load balancer to share traffic between multiple servers. Our monitoring should
automatically detect a failure on our backend and automatically remove the affected server from service
so you receiving connectivity timeouts caused by us, to be very few and far between. 

The crashcatch PHP library won't detect PHP code compiler issues. This is because the script never
actually executes in this case so crashcatch isn't available. 

For example, if you missed a semicolon (;) on the end of a line yoy will receive
a parse error, but because this stops the actual PHP script from being processed, this will
not be picked up by crashcatch. 

Sign up for a free account by visiting https://crashcatch.com

crashcatch - Copyright &copy; 2021 - Boardies IT Solutions

<img src="https://boardiesitsolutions.com/images/logo.png"> 
