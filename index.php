<?php
session_start();
if (!isset($_SESSION['username'])) { header("Location: login.php"); exit(); }
include 'db.php';

if (!file_exists('uploads')) { mkdir('uploads', 0777, true); }

// --- ADMIN EDIT LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editID'])) {
    $id = $_POST['editID'];
    $name = $_POST['itemName'];
    $desc = $_POST['description'];
    $room = $_POST['labRoom'];
    
    if (!empty($_FILES['itemImage']['name'])) {
        $targetDir = "uploads/";
        $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["itemImage"]["name"]));
        $targetFilePath = $targetDir . $fileName;
        if (move_uploaded_file($_FILES["itemImage"]["tmp_name"], $targetFilePath)) {
            $stmt = $pdo->prepare("UPDATE items SET item_name=?, lab_room=?, description=?, item_image=? WHERE id=?");
            $stmt->execute([$name, $room, $desc, $targetFilePath, $id]);
        }
    } else {
        $stmt = $pdo->prepare("UPDATE items SET item_name=?, lab_room=?, description=? WHERE id=?");
        $stmt->execute([$name, $room, $desc, $id]);
    }
    header("Location: index.php");
    exit();
}

// --- ORIGINAL SEARCH LOGIC ---
$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM items WHERE status != 'claimed'";
if ($search) {
    $sql .= " AND (item_name LIKE :s OR lab_room LIKE :s OR description LIKE :s)";
}
$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
if ($search) { $stmt->execute(['s' => "%$search%"]); } else { $stmt->execute(); }
$items = $stmt->fetchAll();

// --- ORIGINAL POST LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['itemImage']) && !isset($_POST['editID'])) {
    $targetDir = "uploads/";
    $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["itemImage"]["name"]));
    $targetFilePath = $targetDir . $fileName;
    if (move_uploaded_file($_FILES["itemImage"]["tmp_name"], $targetFilePath)) {
        $stmt = $pdo->prepare("INSERT INTO items (item_name, lab_room, description, item_image, status) VALUES (?, ?, ?, ?, 'available')");
        $stmt->execute([$_POST['itemName'], $_POST['labRoom'], $_POST['description'], $targetFilePath]);
        header("Location: index.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Find IT Lab | Midterm Portal</title>
    <style>
        :root { --it-blue: #1e3a8a; --lab-gray: #f1f5f9; --success: #22c55e; --danger: #ef4444; --warning: #f59e0b; --dark: #0f172a; }
        body { margin: 0; font-family: 'Inter', sans-serif; display: flex; background: var(--lab-gray); min-height: 100vh; }
        
        #sidebar { width: 280px; background: var(--it-blue); color: white; height: 100vh; padding: 30px; position: fixed; display: flex; flex-direction: column; box-sizing: border-box; overflow-y: auto; }
        .insight-box { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; margin-bottom: 20px; }
        #audit-log { font-size: 11px; color: #cbd5e1; max-height: 200px; overflow-y: auto; }
        .log-item { border-bottom: 1px solid rgba(255,255,255,0.1); padding: 5px 0; }

        #main { margin-left: 280px; padding: 40px; flex-grow: 1; box-sizing: border-box; }
        .admin-box { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #e2e8f0; }
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; flex: 1; }
        input, select, textarea { padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }

        .ticket-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; }
        .ticket-card { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .card-img { width: 100%; height: 180px; object-fit: cover; }
        .card-body { padding: 20px; }
        
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .status-available { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .item-desc { color: #64748b; font-size: 13px; margin: 10px 0; min-height: 40px; }

        .btn { width: 100%; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 5px; transition: 0.2s; }
        .btn-claim { background: var(--dark); color: white; }
        .btn-accept { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-outline { background: #f1f5f9; color: #64748b; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 500px; max-width: 90%; }
    </style>
</head>
<body>

<div id="sidebar">
    <h2>Find IT Lab</h2>
    <div style="margin-bottom: 20px;">User: <strong style="color: #ffcc00;"><?php echo $_SESSION['username']; ?></strong></div>
    <div class="insight-box">
        <small>SYSTEM AUDIT TRAIL</small>
        <div id="audit-log">
            <?php
            $logs = $pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5")->fetchAll();
            foreach($logs as $l) {
                echo "<div class='log-item'>[" . date('H:i', strtotime($l['action_date'])) . "] " . htmlspecialchars($l['action_text']) . "</div>";
            }
            ?>
        </div>
    </div>
</div>

<div id="main">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1>Dashboard</h1>
        <button class="btn" style="width: 80px; background:var(--danger); color:white;" onclick="location.href='logout.php'">Logout</button>
    </div>

    <div class="search-container" style="margin-bottom: 25px;">
        <form method="GET" style="display:flex; width:100%; gap:10px;">
            <input type="text" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>" style="flex-grow:1;">
            <button type="submit" class="btn" style="margin:0; width:120px; background:var(--it-blue); color:white;">Filter</button>
        </form>
    </div>

    <?php if ($_SESSION['role'] == 'admin'): ?>
    <div class="admin-box">
        <form action="index.php" method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group"><label>ITEM NAME</label><input type="text" name="itemName" required></div>
                <div class="form-group"><label>ROOM</label><select name="labRoom"><option>Lab 1</option><option>Lab 2</option></select></div>
                <div class="form-group"><label>PHOTO</label><input type="file" name="itemImage" accept="image/*" required></div>
            </div>
            <div class="form-row">
                <textarea name="description" required style="flex:2;" placeholder="Describe location..."></textarea>
                <button type="submit" class="btn" style="background:var(--it-blue); color:white; width:150px; margin:0;">Broadcast</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="ticket-grid" id="ticket-area">
        <?php foreach ($items as $row) { ?>
            <div class="ticket-card" id="card-<?php echo $row['id']; ?>">
                <img src="<?php echo $row['item_image']; ?>" class="card-img">
                <div class="card-body">
                    <span class="status-badge <?php echo ($row['status'] == 'available') ? 'status-available' : 'status-pending'; ?>">
                        <?php echo strtoupper($row['status']); ?>
                    </span>
                    <h3 style="margin:10px 0 5px 0;"><?php echo htmlspecialchars($row['item_name']); ?></h3>
                    <p class="item-desc"><?php echo htmlspecialchars($row['description'] ?? ''); ?></p>

                    <div id="action-area-<?php echo $row['id']; ?>">
                        <?php if ($_SESSION['role'] == 'student' && $row['status'] == 'available'): ?>
                            <button class="btn btn-claim" onclick="openClaimModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['item_name']); ?>')">Claim Item</button>
                        <?php elseif ($_SESSION['role'] == 'admin'): ?>
                            <button class="btn btn-danger" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['item_name']); ?>')">Remove Ticket</button>
                            <button class="btn btn-outline" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['item_name']); ?>', '<?php echo addslashes($row['description']); ?>', '<?php echo $row['lab_room']; ?>')">Edit Details</button>
                            
                            <?php if ($row['status'] == 'pending'): ?>
                                <button class="btn btn-accept" style="margin-top:10px;" onclick="openApproveModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['item_name']); ?>')">Review Approval</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <h2>Edit Ticket</h2>
        <form action="index.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="editID" id="editID">
            <div class="form-group"><label>Item Name</label><input type="text" name="itemName" id="editName" required></div>
            <div class="form-group" style="margin-top:10px;"><label>Room</label><select name="labRoom" id="editRoom"><option>Lab 1</option><option>Lab 2</option></select></div>
            <div class="form-group" style="margin-top:10px;"><label>Description</label><textarea name="description" id="editDesc" style="height:80px;"></textarea></div>
            <div class="form-group" style="margin-top:10px;"><label>Update Image (Optional)</label><input type="file" name="itemImage" accept="image/*"></div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn-accept" style="margin:0;">Save Changes</button>
                <button type="button" class="btn btn-outline" style="margin:0;" onclick="closeModal('editModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="claimModal" class="modal">
    <div class="modal-content">
        <h2>Claim Request</h2>
        <input type="text" id="studentID" placeholder="Enter Student ID" style="width: 100%; margin-bottom: 10px; box-sizing: border-box;">
        <textarea id="studentMsg" placeholder="Message to Admin..." style="width: 100%; height: 80px; margin-bottom: 10px; box-sizing: border-box;"></textarea>
        <button class="btn btn-accept" onclick="confirmClaimRequest()">Send Request</button>
        <button class="btn btn-outline" onclick="closeModal('claimModal')">Cancel</button>
    </div>
</div>

<div id="approveModal" class="modal">
    <div class="modal-content">
        <h2>Confirm Release</h2>
        <p>Are you sure you want to release <strong id="approveItemName"></strong> to the student?</p>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-accept" onclick="executeApprove()">Approve & Release</button>
            <button class="btn btn-outline" onclick="closeModal('approveModal')">Cancel</button>
        </div>
    </div>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h2 style="color: var(--danger);">Delete Ticket?</h2>
        <p>Are you sure you want to remove <strong id="deleteItemName"></strong>?</p>
        <button class="btn btn-danger" onclick="executeDelete()">Yes, I'm Sure</button>
        <button class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
    </div>
</div>

<script>
    const socket = new WebSocket('ws://localhost:8080');
    let currentId = null, currentName = '';

    // Modal Controls
    function openEditModal(id, name, desc, room) {
        document.getElementById('editID').value = id;
        document.getElementById('editName').value = name;
        document.getElementById('editDesc').value = desc;
        document.getElementById('editRoom').value = room;
        document.getElementById('editModal').style.display = 'flex';
    }

    function openClaimModal(id, name) { currentId = id; currentName = name; document.getElementById('claimModal').style.display = 'flex'; }
    
    function openApproveModal(id, name) { 
        currentId = id; 
        document.getElementById('approveItemName').innerText = name; 
        document.getElementById('approveModal').style.display = 'flex'; 
    }

    function confirmDelete(id, name) { 
        currentId = id; 
        document.getElementById('deleteItemName').innerText = name;
        document.getElementById('deleteModal').style.display = 'flex'; 
    }

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    // Socket Actions
    function confirmClaimRequest() {
        socket.send(JSON.stringify({ 
            type: 'REQUEST_CLAIM', 
            id: currentId, 
            student_id: document.getElementById('studentID').value, 
            message: document.getElementById('studentMsg').value, 
            item_name: currentName 
        }));
        closeModal('claimModal');
        // --- AUTO REFRESH FOR THE SENDER ---
        setTimeout(() => { location.reload(); }, 300); 
    }

    function executeApprove() {
        socket.send(JSON.stringify({ type: 'APPROVE_CLAIM', id: currentId }));
        closeModal('approveModal');
    }

    function executeDelete() {
        socket.send(JSON.stringify({ type: 'DELETE_ITEM', id: currentId }));
        closeModal('deleteModal');
    }

    // LISTENER FOR OTHERS
    socket.onmessage = (e) => {
        const data = JSON.parse(e.data);
        if (['ITEM_PENDING', 'ITEM_FINALIZED', 'ITEM_DELETED', 'REFRESH'].includes(data.type)) {
            location.reload(); 
        }
    };
</script>
</body>
</html>