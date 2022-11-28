<?php

use PhpAmqpLib\Message\AMQPMessage;

function onTrade(AMQPMessage $msgIn) {
    global $debug;
     
    try {
        $body = json_decode($msgIn -> body, true);
        $headers = $msgIn->get('application_headers')->getNativeData();
    
        if($debug) echo "Headers:\n".json_encode($headers, JSON_PRETTY_PRINT)."\nBody:\n".$msgIn->body."\n\n";
        
        echo $headers['pairid'];
        echo $msgIn -> body['price'];
        
        $msgIn -> ack();
    }
    catch(Exception $e) {
        $msgIn -> reject(true);
        throw $e;
    }
}

?>