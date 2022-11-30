<?php

use PhpAmqpLib\Wire;
use PhpAmqpLib\Message\AMQPMessage;

function updateMarket($pairid, $price, $amount, $total) {
    global $debug, $pdo, $redis;
     
    $task = array(
        ':pairid' => $pairid,
        ':price' => $price,
        ':price2' => $price,
        ':amount' => $amount,
        ':total' => $total
    );
    
    $sql = 'UPDATE spot_tickers_v2_data
            SET refresh_time = current_timestamp,
                previous = price,
                price = :price,
                change = ROUND(
                    (:price2 - change_reference)
                    / change_reference
                    * 100
                ),
                vol_base = vol_base + :amount,
                vol_quote = vol_quote + :total
            WHERE pairid = :pairid
            RETURNING *';
    
    $q = $pdo -> prepare($sql);
    $q -> execute($task);
    $row = $q -> fetch(PDO::FETCH_ASSOC);
    
    emitAggTicker($row);
    
    if($debug) echo 'Updated market '.$pairid."\n";
    
    /*if($redis) {
        $redis -> unlink($redis -> keys('spot:markets:*'));
    }*/
}

function rebuildMarkets() {
    global $debug, $pdo, $redis;
    
    if($debug) echo "Update all markets\n";
    
    $sql = 'SELECT pairid FROM spot_markets';
    $q = $pdo -> query($sql);
    
    while($pair = $q -> fetch(PDO::FETCH_ASSOC)) {
        if($debug) echo 'Processing '.$pair['pairid']."\n";
        
        $task = array(
            ':pairid' => $pair['pairid']
        );
        
        $sql = "SELECT LAST(price, time) AS price
                FROM spot_trades_with_initial
                WHERE time < NOW() - INTERVAL '1 day'
                AND pairid = :pairid";
        
        $q2 = $pdo -> prepare($sql);
        $q2 -> execute($task);
        $before24h = $q2 -> fetch(PDO::FETCH_ASSOC);
        
        $sql = "SELECT MAX(price) AS high,
                       MIN(price) AS low,
                       SUM(amount) AS vol_base,
                       SUM(total) AS vol_quote
                FROM spot_trades
                WHERE time >= NOW() - INTERVAL '1 day'
                AND pairid = :pairid";
        
        $q2 = $pdo -> prepare($sql);
        $q2 -> execute($task);
        $last24h = $q2 -> fetch(PDO::FETCH_ASSOC);
        
        if($last24h['high'] == NULL) {
            $last24h['high'] = $before24h['price'];
            $last24h['low'] = $before24h['price'];
            $last24h['vol_base'] = '0';
            $last24h['vol_quote'] = '0';
        }
        
        $task = array(
            ':pairid' => $pair['pairid'],
            ':change_reference' => $before24h['price'],
            ':change_reference2' => $before24h['price'],
            ':change_reference3' => $before24h['price'],
            ':vol_base' => $last24h['vol_base'],
            ':vol_quote' => $last24h['vol_quote'],
            ':high' => $last24h['high'],
            ':low' => $last24h['low']
        );
        
        $sql = 'UPDATE spot_tickers_v2_data
                SET refresh_time = current_timestamp,
                    change_reference = :change_reference,
                    change = ROUND(
                        (price - :change_reference2)
                        / :change_reference3
                        * 100
                    ),
                    high = :high,
                    low = :low,
                    vol_base = :vol_base,
                    vol_quote = :vol_quote
                WHERE pairid = :pairid
                RETURNING *';
        
        $q2 = $pdo -> prepare($sql);
        $q2 -> execute($task);
        $row = $q2 -> fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            emitAggTicker($row);
        }
        else {
            if($debug) echo "Need to insert\n";
            
            $task = array(
                ':pairid' => $pair['pairid']
            );
            
            $sql = "SELECT LAST(price, time) AS price
                    FROM spot_trades_with_initial
                    WHERE pairid = :pairid";
            
            $q2 = $pdo -> prepare($sql);
            $q2 -> execute($task);
            $lastEver = $q2 -> fetch(PDO::FETCH_ASSOC);
            
            $task = array(
                ':pairid' => $pair['pairid'],
                ':init' => $lastEver['price'],
                ':init2' => $lastEver['price'],
                ':init3' => $lastEver['price'],
                ':init4' => $lastEver['price'],
                ':init5' => $lastEver['price']
            );
            
            $sql = 'INSERT INTO spot_tickers_v2_data(
                        pairid,
                        refresh_time,
                        price,
                        change,
                        high,
                        low,
                        vol_base,
                        vol_quote,
                        previous,
                        change_reference
                    )
                    VALUES(
                        :pairid,
                        current_timestamp,
                        :init,
                        0,
                        :init2,
                        :init3,
                        0,
                        0,
                        :init4,
                        :init5
                    )';
            
            $q2 = $pdo -> prepare($sql);
            $q2 -> execute($task);
        }
    }
    
    /*if($redis) {
        $redis -> unlink($redis -> keys('spot:markets:*'));
    }*/
}

function emitAggTicker($tickRow) {
    global $channel;
    
    $headers = new Wire\AMQPTable([
        'event' => 'aggTicker',
        'pairid' => $tickRow['pairid'],
    ]);
    
    $body = array(
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