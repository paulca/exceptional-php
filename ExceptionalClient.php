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
            $request .= "Accept: */*\r\n";
            $request .= "User-Agent: exception-php-client 0.1\r\n";
            $request .= "Content-Type: text/xml\r\n";
            $request .= "Content-Length: ".strlen($post_data)."\r\n\r\n";
            $request .= "$post_data\r\n";
        } else {
            $request .= "\r\n";
        }

        var_dump($request);
flush();
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
        $now = date("D M j H:i:s O Y");
        $env = $this->envToXML();
        $session = $this->sessionToXML();
        $request_parameters = $this->requestToXML();
        
        $trace = $this->exception->getTrace();
        $class = $trace[0]["class"];
        $function = $trace[0]["function"];
        $message = $this->exception->getMessage();
        $backtrace = $this->exception->getTraceAsString();
		$error_class = get_class($this->exception);

        return 
"<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<error>
  <agent_id>cc25f30e09d5d2e14cbdc7b0e1da30ba0896a58c</agent_id>
  <controller_name>$class</controller_name>
  <error_class>$error_class</error_class>
  <action_name>$function</action_name>
  <environment>
$env
  </environment>
  <session>

  </session>
  <rails_root>/</rails_root>
  <url>$this->request_uri</url>
  <parameters>

  </parameters>
  <occurred_at>$now</occurred_at>
  <message>$message</message>
  <backtrace>$backtrace</backtrace>
</error>";
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
        if(!is_array($array) || empty($array)) {
            return "   <no>values</no>";
        }

        $return_value = array();
        foreach($array AS $key => $value) {
			$key = strToLower($key);
            $return_value[] = "    <$key>$value</$key>";
        }
        
        return implode("\n", $return_value);
    }
}
/*
POST to http://getexceptional.com/errors/?api_key=1234yuio123yiuo&protocol_version=2

<?xml version="1.0" encoding="UTF-8"?><error><agent_id>cc25f30e09d5d2e14cbdc7b0e1da30ba0896a58c</agent_id>
<controller_name>Foo</controller_name>
<action_name>bar</action_name>
<error_class>DodgyException</error_class>
<message>this is awesome</message>
<backtrace>the craziness</backtrace>
<occurred_at>Thu Sep 11 16:05:53 -0400 2008</occurred_at>
<rails_root>/var/www/woo/woo/woo</rails_root>
<url>http://www.tryme.com</url>
<environment>
 <server_name>lovely</server_name>
 <foo>nice</foo>
</environment>
<session>
 <foo>bar</foo>
</session>
<parameters>POST DATA</parameters>
</error>"
*/