<?php

require __DIR__.'/Tickers.php';
require __DIR__.'/Orderbooks.php';
require __DIR__.'/Trades.php';

class App extends Infinex\App\Daemon {
    private $pdo;
    private $redis;
    private $tickers;
    private $orderbooks;
    private $trades;
    
    function __construct() {
        parent::__construct('spot.aggregator');
        
        $this -> pdo = new Infinex\Database\PDO($this -> loop, $this -> log);
        $this -> pdo -> start();
        
        $this -> redis = new Clue\React\Redis\RedisClient(REDIS_HOST.':'.REDIS_PORT);
        
        $this -> tickers = new Tickers($this -> loop, $this -> log, $this -> pdo, $this -> redis);
        $this -> orderbooks = new Orderbooks($this -> loop, $this -> log, $this -> pdo, $this -> redis);
        $this -> trades = new Trades($this -> loop, $this -> log, $this -> redis);
        
        $th = $this;
        $this -> amqp -> on('connect', function() use($th) {
            $th -> auth -> bind($th -> amqp);
        });
    }
}

?>