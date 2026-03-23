<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
require __DIR__ . '/vendor/autoload.php';
include 'db.php';

class LostAndFoundServer implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "Server Started... Listening!\n";
    }

    public function onOpen(ConnectionInterface $conn) { $this->clients->attach($conn); }
    public function onClose(ConnectionInterface $conn) { $this->clients->detach($conn); }
    public function onError(ConnectionInterface $conn, \Exception $e) { $conn->close(); }

    public function onMessage(ConnectionInterface $from, $msg) {
        global $pdo;
        $data = json_decode($msg, true);
        if (!$data) return;

        if ($data['type'] === 'REQUEST_CLAIM') {
            $sid = htmlspecialchars($data['student_id']);
            $msg = htmlspecialchars($data['message']);
            $stmt = $pdo->prepare("UPDATE items SET status='pending', claimed_by=?, student_message=? WHERE id=?");
            $stmt->execute([$sid, $msg, $data['id']]);
            
            $log = $pdo->prepare("INSERT INTO audit_logs (action_text, user_involved) VALUES (?, ?)");
            $log->execute(["Requested claim for " . $data['item_name'], $sid]);
            $this->broadcast($data);
        }

        if ($data['type'] === 'APPROVE_CLAIM') {
            $pdo->prepare("UPDATE items SET status='claimed' WHERE id=?")->execute([$data['id']]);
            $log = $pdo->prepare("INSERT INTO audit_logs (action_text, user_involved) VALUES (?, ?)");
            $log->execute(["Admin approved release: " . $data['item_name'], "ADMIN"]);
            $this->broadcast(['type'=>'ITEM_FINALIZED', 'id'=>$data['id'], 'item_name'=>$data['item_name']]);
        }

        if ($data['type'] === 'DELETE_ITEM') {
            $pdo->prepare("DELETE FROM items WHERE id=?")->execute([$data['id']]);
            $log = $pdo->prepare("INSERT INTO audit_logs (action_text, user_involved) VALUES (?, ?)");
            $log->execute(["Admin DELETED item: " . $data['item_name'], "ADMIN"]);
            $this->broadcast(['type'=>'ITEM_DELETED', 'id'=>$data['id'], 'item_name'=>$data['item_name']]);
        }
    }

    protected function broadcast($data) {
        foreach ($this->clients as $client) { $client->send(json_encode($data)); }
    }
}

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
$server = IoServer::factory(new HttpServer(new WsServer(new LostAndFoundServer())), 8080);
$server->run();