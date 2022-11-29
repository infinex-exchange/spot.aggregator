<?php

use PhpAmqpLib\Wire;
use PhpAmqpLib\Message\AMQPMessage;

function emitAggTicker($tickRow) {
    $headers = new Wire\AMQPTable([
        'event' => 'aggTicker',
        'pairid' => $tickRow['pairid'],
    ]);
    
    $tmpEvent['body'] = array(
        'price' => $tickRow['price'],
        'change' => $tickRow['change'],
        'previous' => $tickRow['previous'],
        'high' => $tickRow['high'],
        'low' => $tickRow['low'],
        'vol_base' => $tickRow['vol_base'],
        'vol_quote' => $tickRow['vol_quote']
    );
    
    $outMsg = new AMQPMessage(json_encode($body, JSON_PRETTY_PRINT));
    $outMsg -> set('application_headers', $headers);
    $channel -> basic_publish($outMsg, 'outEvents');
}

?>