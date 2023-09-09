<?php

class Orderbooks {
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

    public function updateOrderbook($pairid, $side, $price, $sign, $amount) {
        $this -> pdo -> beginTransaction();
         
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
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        $row = $q -> fetch();
        
        $newAmount = NULL;
        
        if($row) {
            $newAmount = $row['amount'];
            
            if(trim($row['amount'], '0.') == '') { // amount == 0, without Decimal
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
            
            $q = $this -> pdo -> prepare($sql);
            $q -> execute($task);
        }
        
        $this -> pdo -> commit();
        
        $this -> redis -> unlink("spot:orderbook:$pairid:$side");
        
        $this -> emitAggOrderbook($pairid, $side, $price, $newAmount);
        
    }
    
    function rebuildOrderbooks() {
        $this -> log -> info('Rebuilding all orderbooks');
        
        $this -> pdo -> beginTransaction();
        
        $sql = 'TRUNCATE TABLE spot_aggregated_orderbook_v2';
        $this -> pdo -> query($sql);
        
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
        $this -> pdo -> query($sql);
        
        $this -> pdo -> commit();
        
        $th = $this;
        $this -> redis -> keys('spot:orderbook:*') -> then(
            function($keys) use($th)
                $th -> redis -> unlink(...$keys);
            }
        );
    }
    
    private function emitAggOrderbook($pairid, $side, $price, $amount) {
        $this -> amqp -> pub(
            'agg_orderbook',
            [
                'side' => $side,
                'price' => $price,
                'amount' => $amount
            ],
            [
                'pairid' => $pairid
            ]
        );
    }
}

?>