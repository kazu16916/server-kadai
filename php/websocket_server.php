<?php
require 'vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use MyApp\VoteServer;

// 8080番ポートでサーバーを起動
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new VoteServer()
        )
    ),
    8080
);

$server->run();