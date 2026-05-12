<?php
include 'db.php';
session_start();

// --- 1. CONNECTED PAGINATION ---
$limit = 6; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Uses your actual 'items' table
$total_stmt = $pdo->query("SELECT count(*) FROM items");
$total_items = $total_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

// Fetches the same items the students see
$stmt = $pdo->prepare("SELECT * FROM items ORDER BY id DESC LIMIT :start, :limit");
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

// Uses your 'audit_logs' table for the sidebar
$audit_stmt = $pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5");
$audits = $audit_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Find IT Lab - Admin Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f1f5f9; display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: #1e293b; color: white; padding: 25px; }
        .logo-section { text-align: center; margin-bottom: 30px; }
        .logo-section img { width: 90px; height: 90px; border-radius: 50%; border: 3px solid #3b82f6; }
        .audit-box { background: #334155; padding: 15px; border-radius: 10px; margin-top: 20px; }
        .audit-entry { font-size: 0.8rem; padding: 8px 0; border-bottom: 1px solid #475569; }
        .main-content { flex: 1; padding: 40px; }
        .add-item-box { background: white; padding: 20px; border-radius: 12px; display: flex; gap: 15px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .items-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .card img { width: 100%; height: 180px; object-fit: cover; }
        .card-body { padding: 15px; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 30px; }
        .page-link { padding: 8px 16px; background: white; border: 1px solid #ccc; text-decoration: none; border-radius: 5px; color: black; }
        .page-link.active { background: #3b82f6; color: white; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-section">
            <img src="logo.png" alt="Logo">
            <h2 style="margin-top:10px;">Find IT Lab</h2>
            <p style="color:#94a3b8">User: <span style="color:#fbbf24">admin</span></p>
        </div>

        <div class="audit-box">
            <p style="font-size:0.7rem; color:#94a3b8; text-transform:uppercase;">System Audit Trail</p>
            <?php foreach ($audits as $log): ?>
                <div class="audit-entry">
                    <span style="color:#60a5fa;">[<?php echo date('H:i', strtotime($log['created_at'])); ?>]</span>
                    <?php echo htmlspecialchars($log['action_text']); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="main-content">
        <div class="add-item-box">
            <input type="text" id="itemName" placeholder="ITEM NAME" style="flex:2; padding:10px;">
            <select id="itemRoom" style="flex:1; padding:10px;">
                <option>Lab 1</option>
                <option>Lab 2</option>
            </select>
            <input type="file" id="itemPhoto" style="flex:2;">
            <button onclick="broadcastItem()" style="background:#1e293b; color:white; padding:10px 20px; border-radius:6px; cursor:pointer;">Broadcast</button>
        </div>

        <div class="items-grid">
            <?php foreach ($items as $item): ?>
                <div class="card">
                    <img src="<?php echo $item['image_path']; ?>" alt="Item">
                    <div class="card-body">
                        <p style="font-size:0.7rem; color:green; font-weight:bold;"><?php echo strtoupper($item['status']); ?></p>
                        <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
                        <p style="color:#64748b; font-size:0.9rem;"><?php echo htmlspecialchars($item['description']); ?></p>
                        <button onclick="removeItem(<?php echo $item['id']; ?>)" style="width:100%; margin-top:10px; padding:8px; background:#ef4444; color:white; border:none; border-radius:5px; cursor:pointer;">Remove Ticket</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="page-link <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <script>
        // CONNECT TO YOUR WEBSOCKET SERVER
        const conn = new WebSocket('ws://localhost:8080');

        function broadcastItem() {
            const name = document.getElementById('itemName').value;
            // Send to your SocketHandler.php logic
            conn.send(JSON.stringify({
                type: 'NEW_TICKET',
                item: name,
                lab: document.getElementById('itemRoom').value,
                desc: 'Added via Admin Dashboard',
                admin: 'admin'
            }));
            alert('Item Broadcasted!');
            location.reload(); 
        }

        function removeItem(id) {
            conn.send(JSON.stringify({
                type: 'DELETE_ITEM',
                id: id,
                item_name: 'Item' 
            }));
            alert('Item Removed!');
            location.reload();
        }
    </script>
</body>
</html>