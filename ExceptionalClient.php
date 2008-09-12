<?php
/**
 * Exception handler and client for getexceptional.com
 *
 * @author Jan Lehnardt <jan@php.net>
 **/
class ExceptionalClient
{
    /**
     * Installs the ExceptinoalClient class as the default exception handler
     *
     **/
    function __construct($api_key)
    {
        $this->url = "/errors/?api_key={$api_key}&protocol_version=2";
        $this->host = "getexceptional.com";
        $this->port = 80;

        // set exception handler & keep old exception handler around
        $this->previous_exception_handler = set_exception_handler(array($this, "handle_exception"));
    }
    
    function handle_exception($exception)
    {
        
        $this->exceptions[] = new ExceptionData($exception);
        if($this->previous_exception_handler) {
            $this->previous_exception_handler();
        }
    }
    
    function __destruct()
    {
        echo "sending exceptions:";
        // send stack of exceptions to getexceptional
        foreach($this->exceptions AS $exception) {
            $this->send_exception($exception);
        }
    }
    
    function send_exception($exception)
    {
        $body = $exception->toXML();
        
        $this->post($this->url, $body);
    }
    
    function post($url, $post_data)
    {
        $s = fsockopen($this->host, $this->port, $errno, $errstr); 

        if(!$s) { 
            echo "$errno: $errstr\n";
            return false;
        }

        $request = "POST $url HTTP/1.1\r\nHost: $this->host\r\n";

        if($post_data) {
            $request .= "Content-Length: ".strlen($post_data)."\r\n\r\n";
            $request .= "$post_data\r\n";
        } else {
            $request .= "\r\n";
        }

        var_dump($request);
        fwrite($s, $request);

        $response = "";
        while(!feof($s)) {
            $response .= fgets($s);
        }

        var_dump($response);
        list($this->headers, $this->body) = explode("\r\n\r\n", $response);
        // return $this->body;
    }
}

class ExceptionData
{
    function __construct($exception)
    {
        $this->exception = $exception;
        $this->user_ip = $_SERVER["REMOTE_ADDR"];
        $this->host_ip = $_SERVER["SERVER_ADDR"];
        $this->request_method = $_SERVER["REQUEST_METHOD"];
        $this->request_uri = $_SERVER["REQUEST_URI"];
    }
    
    function toXML()
    {
        $now = time();
        $env = $this->envToXML();
        $session = $this->sessionToXML();
        $request_parameters = $this->requestToXML();
        
        $trace = $this->exception->getTrace();
        $class = $trace[0]["class"];
        $function = $trace[0]["function"];
        $message = $this->exception->getMessage();
        

        return <<< EOD
<error>
  <controller_name>$class</controller_name>
  <action_name>$function</action_name>
  <user_ip>$this->user_ip</user_ip>
  <host_ip>$this->host_ip</host_ip>
  <environment>
$env
  </environment>
  <session>
$session
  </session>
  <request_method>$this->request_method</request_method>
  <request_uri>$this->request_uri</request_uri>
  <request_parameters>
$request_parameters
  </request_parameters>
  <occurred_at>$now</occurred_at>
  <summary>$message</summary>
</error>
EOD;
    }
    
    
    function envToXML()
    {
        return $this->_arrayToXML($_ENV);
    }
    
    function sessionToXML()
    {
        return $this->_arrayToXML($_SESSION);
    }
    
    function requestToXML()
    {
        return $this->_arrayToXML($_REQUEST);
    }

    function _arrayToXML($array)
    {
        if(!is_array($array)) {
            return "";
        }
        $return_value = array();
        foreach($array AS $key => $value) {
            $return_value[] = "    <$key>$value</$key>";
        }
        
        return implode("\n", $return_value);
    }
}
/*
sending exceptions:Array
(
    [0] => ExceptionData Object
        (
            [exception] => Exception Object
                (
                    [message:protected] => foo
                    [string:private] => 
                    [code:protected] => 0
                    [file:protected] => /Users/jan/Desktop/ex_test.php
                    [line:protected] => 11
                    [trace:private] => Array
                        (
                            [0] => Array
                                (
                                    [file] => /Users/jan/Desktop/ex_test.php
                                    [line] => 16
                                    [function] => bar
                                    [class] => Foo
                                    [type] => ->
                                    [args] => Array
                                        (
                                        )

                                )

                        )

                )

        )

)

POST to http://getexceptional.com/errors/?api_key=1234yuio123yiuo&protocol_version=2

# Protocol VERSION 2
# <error>
#   <controller_name>..</controller_name>
#   <action_name>...</action_name>
#   <user_ip>...</user_ip>
#   <host_ip>...</host_ip>
#   <environment>
#     <key>value</key>
#   </environment>
#   <session>
#     <key>value</key>
#   </session>
#   <request_method>GET</request_method>
#   <request_uri>http://...</request_uri>
#   <request_parameters>
#     <key>value</key>
#   </request_parameters>
#   <occurred_at>...</occurred_at>
#   <summary>...</summary>
# </error>
*/