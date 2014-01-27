<?php

/* Based on the PHPWebSocket example by Flynsarmy (https://github.com/Flynsarmy/PHPWebSocket-Chat)
*/

// Prevent the server from timing out
set_time_limit(0);

require 'vendor/autoload.php';
require 'PHPWebSocket.php';

// HTTP response header fields
define('HEADER_LOCATION', 'location');
define('HEADER_LENGTH', 'content-length');

// When a message from client is received
function wsOnMessage($clientID, $message, $messageLength, $binary) {
	global $Server;
	$ip = long2ip( $Server->wsClients[$clientID][6] );

	// Close on empty message
	if ($messageLength == 0) {
		$Server->wsClose($clientID);
		return;
	}

    // We have to fork at this point in order to keep listening for new messages
    $pid = pcntl_fork();
    if ($pid == -1) {
        die('Could not fork');
    } 
    else if ($pid) {
        // Parent process, finish
        return;
    } else {
        // Child process, use React library to make asynchronous HTTP request
        $loop = React\EventLoop\Factory::create();
        $dnsResolverFactory = new React\Dns\Resolver\Factory();
        $dnsResolver = $dnsResolverFactory->createCached('8.8.8.8', $loop);

        $factory = new React\HttpClient\Factory();
        $client = $factory->create($loop, $dnsResolver);

        /* Send a request for each URL. We try to save some bandwidth by making HEAD requests first. 
           Only send full request if HEAD response doesn't return Content-Length field or if 405 response is received. */
        $urls = explode("\n", $message);
        foreach ($urls as $url) {
            makeHttpRequest($url, 'HEAD', $client, $clientID);
        }

        $loop->run(); 
    }
}

// Send HTTP request
function makeHttpRequest($url, $requestType, $client, $clientID) {
    global $Server;

    // Assume http:// protocol if none is given
    $prefixedUrl = $url;
    if(!preg_match("/^https?:\/\//", $url))
        $prefixedUrl = 'http://' . $url;

    // Initialize reqest and create response listener
    $request = $client->request($requestType, $prefixedUrl);
    $request->on('response', function ($response) use ($Server, $client, $clientID, $requestType, $url, $prefixedUrl) {
        $responseCode = $response->getCode();
        $headerData = $response->getHeaders();
        $headerData = array_change_key_case($headerData, CASE_LOWER);
        $bodyLength = 0;

        // Follow 301 and 302 redirects
        if(($responseCode == 301 || $responseCode == 302) && array_key_exists(HEADER_LOCATION, $headerData)) {
            makeHttpRequest($headerData[HEADER_LOCATION], 'HEAD', $client, $clientID);
            $Server->wsSend($clientID, "$url##$responseCode##" . -1 . "##" . $headerData[HEADER_LOCATION]);
        }
        else {
            // If HEAD response doesn't have Content-Length, retry with GET request
            if((!array_key_exists(HEADER_LENGTH, $headerData) || $responseCode == 405) && $requestType == 'HEAD') 
                makeHttpRequest($url, 'GET', $client, $clientID);
            elseif($requestType == 'HEAD')
                // Otherwise we can send response to the client
                $Server->wsSend($clientID, "$url##$responseCode##" . $headerData[HEADER_LENGTH]);
        }

        // We only care about total length of response body
        $response->on('data', function ($data) use (&$bodyLength, $url) {
            $bodyLength += strlen($data);
        });

        // When GET request is completed, send result to the client
        $response->on('end', function () use ($Server, $clientID, $requestType, $url, $responseCode, &$bodyLength) {
            if($requestType == 'GET') 
                $Server->wsSend($clientID, "$url##$responseCode##$bodyLength");
        });
    });

    $request->on('error', function ($error, $response) use ($Server, $clientID, $url) {
        $Server->wsSend($clientID, "$url##0##-1");
    });

    $request->end();
}

// When client connects
function wsOnOpen($clientID)
{
	global $Server;
	$ip = long2ip( $Server->wsClients[$clientID][6] );

	$Server->log( "$ip ($clientID) has connected." );
}

// When client disconnects
function wsOnClose($clientID, $status) {
	global $Server;
	$ip = long2ip( $Server->wsClients[$clientID][6] );

	$Server->log( "$ip ($clientID) has disconnected." );
}

// Run the server
$Server = new PHPWebSocket();
$Server->bind('message', 'wsOnMessage');
$Server->bind('open', 'wsOnOpen');
$Server->bind('close', 'wsOnClose');
$Server->wsStartServer('127.0.0.1', 9300);

?>
