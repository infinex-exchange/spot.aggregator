<?php

use PhpAmqpLib\Wire;
use PhpAmqpLib\Message\AMQPMessage;

function emitAggOrderbook($pairid, $side, $price, $amount) {
    global $channel;
    
    $headers = new Wire\AMQPTable([
        'event' => 'aggOrderbook',
        'pairid' => $pairid,
    ]);
    
    $body = array(
        'side' => $side,
        'price' => $price,
        'amount' => $amount
    );
    
    $outMsg = new AMQPMessage(json_encode($body, JSON_PRETTY_PRINT));
    $outMsg -> set('application_headers', $headers);
    $channel -> basic_publish($outMsg, 'outEvents');
}

?>