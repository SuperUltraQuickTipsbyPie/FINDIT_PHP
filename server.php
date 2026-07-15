<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class AuditTrailWebSocket implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "🔌 New browser connected! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        global $pdo;
        $data = json_decode($msg, true);
        
        if (!$data) return;

        date_default_timezone_set('Asia/Manila');
        $time = date('H:i');

        try {
            if ($data['type'] === 'UPDATE_UI') {
                // Already handled in PHP form submit, just broadcast refresh trigger to other clients
                echo "Broadcasting UI update for claim: " . $data['item_name'] . "\n";
            } 
            
            elseif ($data['type'] === 'APPROVE_CLAIM') {
                $id = $data['id'];
                $itemName = $data['item_name'];

                // 1. Update database: Status to claimed
                $stmt = $pdo->prepare("UPDATE items SET status='claimed' WHERE id=?");
                $stmt->execute([$id]);

                // 2. Add to audit log
                $logMsg = "Admin approved release: " . $itemName;
                $logStmt = $pdo->prepare("INSERT INTO audit_logs (action_text) VALUES (?)");
                $logStmt->execute([$logMsg]);

                echo "✅ Approved claim for item ID $id ($itemName)\n";
            } 
            
            elseif ($data['type'] === 'DELETE_ITEM') {
                $id = $data['id'];
                $itemName = $data['item_name'];

                // 1. Delete item from database
                $stmt = $pdo->prepare("DELETE FROM items WHERE id=?");
                $stmt->execute([$id]);

                // 2. Add to audit log
                $logMsg = "Admin DELETED item: " . $itemName;
                $logStmt = $pdo->prepare("INSERT INTO audit_logs (action_text) VALUES (?)");
                $logStmt->execute([$logMsg]);

                echo "❌ Deleted item ID $id ($itemName)\n";
            }

            // Broadcast to all connected browsers so they auto-reload instantly!
            foreach ($this->clients as $client) {
                $client->send(json_encode(['status' => 'refresh']));
            }

        } catch (\Exception $e) {
            echo "Error handling message: " . $e->getMessage() . "\n";
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "🔌 Browser disconnected ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$port = $_ENV['PORT'] ?? 8080;
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new AuditTrailWebSocket()
        )
    ),
    $port
);

echo "🚀 FindIT PHP WebSocket server running on port $port...\n";
$server->run();
?>