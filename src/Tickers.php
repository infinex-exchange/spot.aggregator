<?php

class Tickers {
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
    
    public function updateTicker($pairid, $price, $amount, $total) {
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
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        $row = $q -> fetch();
        
        $quote = explode('/', $pairid)[1];
        
        $th = $this;
        $this -> redis -> keys('spot:markets:quote=null:*') -> then(
            function($keys) use($th)
                $th -> redis -> unlink(...$keys);
            }
        );
        $this -> redis -> keys('spot:markets:quote='.$quote.':*') -> then(
            function($keys) use($th)
                $th -> redis -> unlink(...$keys);
            }
        );
        $this -> redis -> keys('spot:markets:quote=*:pair='.$pairid.':*') -> then(
            function($keys) use($th)
                $th -> redis -> unlink(...$keys);
            }
        );
        
        $this -> emitAggTicker($row);
    }
    
    public function rebuildTickers() {
        $this -> log -> info('Rebuilding all tickers');
        
        $sql = 'SELECT pairid FROM spot_markets';
        $q = $this -> pdo -> query($sql);
        
        while($pair = $q -> fetch()) {
            $this -> log -> debug('Rebuilding ticker '.$pair['pairid']);
            
            $task = array(
                ':pairid' => $pair['pairid']
            );
            
            $sql = "SELECT LAST(price, time) AS price
                    FROM spot_trades_with_initial
                    WHERE time < NOW() - INTERVAL '1 day'
                    AND pairid = :pairid";
            
            $q2 = $this -> pdo -> prepare($sql);
            $q2 -> execute($task);
            $before24h = $q2 -> fetch();
            
            $sql = "SELECT MAX(price) AS high,
                           MIN(price) AS low,
                           SUM(amount) AS vol_base,
                           SUM(total) AS vol_quote
                    FROM spot_trades
                    WHERE time >= NOW() - INTERVAL '1 day'
                    AND pairid = :pairid";
            
            $q2 = $this -> pdo -> prepare($sql);
            $q2 -> execute($task);
            $last24h = $q2 -> fetch();
            
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
            
            $q2 = $this -> pdo -> prepare($sql);
            $q2 -> execute($task);
            $row = $q2 -> fetch();
            
            if($row) {
                emitAggTicker($row);
            }
            else {
                $this -> log -> info('Inserting new pair: '.$pair['pairid']);
                
                $task = array(
                    ':pairid' => $pair['pairid']
                );
                
                $sql = "SELECT LAST(price, time) AS price
                        FROM spot_trades_with_initial
                        WHERE pairid = :pairid";
                
                $q2 = $this -> pdo -> prepare($sql);
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
                
                $q2 = $this -> pdo -> prepare($sql);
                $q2 -> execute($task);
            }
        }
        
        $this -> redis -> keys('spot:markets:*') -> then(
            function($keys) use($th)
                $th -> redis -> unlink(...$keys);
            }
        );
    }
    
    private function emitAggTicker($tickRow) {
        $this -> amqp -> pub(
            'agg_ticker',
            [
                'price' => $tickRow['price'],
                'change' => $tickRow['change'],
                'previous' => $tickRow['previous'],
                'high' => $tickRow['high'],
                'low' => $tickRow['low'],
                'vol_base' => $tickRow['vol_base'],
                'vol_quote' => $tickRow['vol_quote']
            ],
            [
                'pairid' => $tickRow['pairid']
            ]
        );
    }
}

?>