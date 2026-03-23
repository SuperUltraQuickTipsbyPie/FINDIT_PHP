<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class SocketHandler implements MessageComponentInterface {
    protected $clients;
    private $pdo;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        // Database connection for the real-time server
        $this->pdo = new \PDO("mysql:host=localhost;dbname=findit_lab", "root", "");
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg);

        if ($data->type == 'NEW_TICKET') {
            // Save Ticket to Database
            $stmt = $this->pdo->prepare("INSERT INTO tickets (item_name, lab_room, description, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data->item, $data->lab, $data->desc, $data->admin]);
            $newId = $this->pdo->lastInsertId();

            // Log Audit Trail
            $log = $this->pdo->prepare("INSERT INTO audit_trail (action_type, performed_by, details) VALUES ('CREATE', ?, ?)");
            $log->execute([$data->admin, "Posted found item: " . $data->item]);

            // Broadcast to all connected users
            $response = json_encode([
                'type' => 'TICKET_ADDED', 
                'id' => $newId, 
                'item' => $data->item, 
                'lab' => $data->lab
            ]);
            foreach ($this->clients as $client) { $client->send($response); }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }
}