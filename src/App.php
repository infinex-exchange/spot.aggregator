<?php

require __DIR__.'/Authenticator.php';

class App extends Infinex\App\Daemon {
    private $pdo;
    private $redis;
    
    function __construct() {
        parent::__construct('auth.api-auth');
        
        $this -> pdo = new Infinex\Database\PDO($this -> loop, $this -> log);
        $this -> pdo -> start();
        
        $this -> redis = new Clue\React\Redis\RedisClient(REDIS_HOST.':'.REDIS_PORT);
        
        $this -> auth = new Authenticator($this -> log, $this -> pdo);
        
        $th = $this;
        $this -> amqp -> on('connect', function() use($th) {
            $th -> auth -> bind($th -> amqp);
        });
    }
}

?>