<?php

use PhpAmqpLib\Message\AMQPMessage;

function onTrade(AMQPMessage $msgIn) {
    global $debug;
     
    try {
        $body = json_decode($msgIn -> body, true);
        $headers = $msgIn->get('application_headers')->getNativeData();
        
        updateMarket($headers['pairid'], $body['price'], $body['amount'], $body['total']);
        updateOrderbook($headers['pairid'], $body['maker_side'], $body['price'], '-', $body['amount']);
        updateTrades($headers['pairid']);
        
        $msgIn -> ack();
    }
    catch(Exception $e) {
        $msgIn -> reject(true);
        throw $e;
    }
}

function onOrderAcceptedCanceled(AMQPMessage $msgIn) {
    global $debug;
     
    try {
        $body = json_decode($msgIn -> body, true);
        $headers = $msgIn->get('application_headers')->getNativeData();
        
        $sign = NULL;
        
        if($headers['event'] == 'orderAccepted' && isset($body['rest'])) {
            $sign = '+';
            $body['amount'] = $body['rest'];
        }
        
        else if($headers['event'] == 'orderUpdate') {
            if(isset($body['triggered']) && $body['triggered'] == true)
                $sign = '+';
            
            else if(isset($body['status']) && $body['status'] == 'CANCELED')
                $sign = '-';
        }
        
        if($sign !== NULL)
            updateOrderbook($headers['pairid'], $body['side'], $body['price'], $sign, $body['amount']);
        
        $msgIn -> ack();
    }
    catch(Exception $e) {
        $msgIn -> reject(true);
        throw $e;
    }
}

?>