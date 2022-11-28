<?php

use PhpAmqpLib\Message\AMQPMessage;

function onTrade(AMQPMessage $msgIn) { 
    try {
        $body = json_decode($msgIn -> body, true);
        $headers = $msgIn->get('application_headers')->getNativeData();
    
        echo "Headers:\n".json_encode($headers, JSON_PRETTY_PRINT)."\nBody:\n".$msgIn->body."\n\n";
        
        $msgIn -> ack();
    }
    catch(Exception $e) {
        $msgIn -> reject(true);
        throw $e;
    }
}

?>