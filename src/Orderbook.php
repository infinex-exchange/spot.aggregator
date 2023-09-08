<?php

use Decimal\Decimal;

class Orderbook {
    private $loop;
    private $log;
    private $pdo;
    private $redis;
    
    function __construct($loop, $log, $pdo, $redis) {
        $this -> loop = $loop;
        $this -> log = $log;
        $this -> pdo = $pdo;
        $this -> redis = $redis;
        
        $this -> log -> debug('Initialized tickers module');
    }

    function updateOrderbook($pairid, $side, $price, $sign, $amount) {
        global $debug, $pdo, $redis;
        
        if($sign != '-' && $sign != '+') return;
        
        $pdo -> beginTransaction();
         
        $task = array(
            ':pairid' => $pairid,
            ':side' => $side,
            ':price' => $price,
            ':amount' => $amount
        );
        
        $sql = 'UPDATE spot_aggregated_orderbook_v2
                SET amount = amount '.$sign.' :amount
                WHERE pairid = :pairid
                AND side = :side
                AND price = :price
                RETURNING amount';
        
        $q = $pdo -> prepare($sql);
        $q -> execute($task);
        $row = $q -> fetch(PDO::FETCH_ASSOC);
        
        $newAmount = NULL;
        
        if($row) {
            $newAmount = $row['amount'];
            
            $amountDec = new Decimal($row['amount']);
            if($amountDec -> isZero()) {
                $task = array(
                    ':pairid' => $pairid,
                    ':side' => $side,
                    ':price' => $price
                );
                
                $sql = 'DELETE FROM spot_aggregated_orderbook_v2
                        WHERE pairid = :pairid
                        AND side = :side
                        AND price = :price';
                
                $q = $pdo -> prepare($sql);
                $q -> execute($task);
            }
        }
        
        else {
            $newAmount = $amount;
            
            $task = array(
                ':pairid' => $pairid,
                ':side' => $side,
                ':price' => $price,
                ':amount' => $amount
            );
            
            $sql = 'INSERT INTO spot_aggregated_orderbook_v2(
                        pairid,
                        side,
                        price,
                        amount
                    )
                    VALUES(
                        :pairid,
                        :side,
                        :price,
                        :amount
                    )';
            
            $q = $pdo -> prepare($sql);
            $q -> execute($task);
        }
        
        $pdo -> commit();
        
        emitAggOrderbook($pairid, $side, $price, $newAmount);
        
        if($debug) echo 'Updated orderbook for '.$pairid."\n";
        
        if($redis)
            $redis -> unlink("spot:orderbook:$pairid:$side");
    }
    
    function rebuildOrderbook() {
        global $debug, $pdo, $redis;
        
        if($debug) echo "Rebuilding orderbook\n";
        
        $pdo -> beginTransaction();
        
        $sql = 'TRUNCATE TABLE spot_aggregated_orderbook_v2';
        $pdo -> query($sql);
        
        $sql = 'INSERT INTO spot_aggregated_orderbook_v2(
                    pairid,
                    side,
                    price,
                    amount
                )
                SELECT pairid,
                       side,
                       price,
                       SUM(amount-filled)
                FROM spot_orderbook
                GROUP BY pairid,
                         side,
                         price';
        $pdo -> query($sql);
        
        $pdo -> commit();
        
        if($redis)
            $redis -> unlink($redis -> keys('spot:orderbook:*'));
    }
    
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
}

?>