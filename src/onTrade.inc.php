<?php

use PhpAmqpLib\Message\AMQPMessage;

function onTrade(AMQPMessage $msgIn) {
    global $debug;
     
    try {
        $body = json_decode($msgIn -> body, true);
        $headers = $msgIn->get('application_headers')->getNativeData();
        
        updateMarket($headers['pairid'], $body['price'], $body['amount'], $body['total']);
        
        $msgIn -> ack();
    }
    catch(Exception $e) {
        $msgIn -> reject(true);
        throw $e;
    }
}

?>