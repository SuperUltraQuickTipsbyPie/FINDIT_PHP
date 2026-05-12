<?php
session_start();
if (!isset($_SESSION['username'])) { header("Location: login.php"); exit(); }
include 'db.php';

if (!file_exists('uploads')) { mkdir('uploads', 0777, true); }

// --- PAGINATION MATH ---
$limit = 6; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// --- NEW: PHP CANCEL LOGIC (Fixes the button not working) ---
if (isset($_GET['action']) && $_GET['action'] == 'cancel_claim') {
    $id = $_GET['id'];
    $itemName = $_GET['item_name'] ?? 'Item';
    
    // Reset item status and clear student data
    $stmt = $pdo->prepare("UPDATE items SET status='available', student_id=NULL, claim_message=NULL WHERE id=?");
    $stmt->execute([$id]);
    
    // Log it in Audit Trail
    $logMsg = "Student cancelled claim for: " . $itemName;
    $logStmt = $pdo->prepare("INSERT INTO audit_logs (action_text) VALUES (?)");
    $logStmt->execute([$logMsg]);

    header("Location: index.php");
    exit();
}

// --- STUDENT CLAIM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submitClaim'])) {
    $id = $_POST['claimItemID'];
    $sid = $_POST['studentID'];
    $msg = $_POST['studentMsg'];
    $stmt = $pdo->prepare("UPDATE items SET status='pending', student_id=?, claim_message=? WHERE id=?");
    $stmt->execute([$sid, $msg, $id]);
}

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

// --- SEARCH & FILTER ---
$search = $_GET['search'] ?? '';
$whereSql = "WHERE status != 'claimed'";
$params = [];
if ($search) {
    $whereSql .= " AND (item_name LIKE :s OR lab_room LIKE :s OR description LIKE :s)";
    $params['s'] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT count(*) FROM items $whereSql");
$countStmt->execute($params);
$total_items = $countStmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

$sql = "SELECT * FROM items $whereSql ORDER BY id DESC LIMIT $start, $limit";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// --- BROADCAST LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['itemImage']) && !isset($_POST['editID']) && !isset($_POST['submitClaim'])) {
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
    <title>Find IT Lab | Portal</title>
    <style>
        :root { --it-blue: #1e3a8a; --lab-gray: #f1f5f9; --success: #22c55e; --danger: #ef4444; --warning: #f59e0b; --dark: #0f172a; }
        body { margin: 0; font-family: 'Inter', sans-serif; display: flex; background: var(--lab-gray); min-height: 100vh; }
        #sidebar { width: 280px; background: var(--it-blue); color: white; height: 100vh; padding: 25px; position: fixed; display: flex; flex-direction: column; box-sizing: border-box; }
        .sidebar-logo-container { width: 110px; height: 110px; border: 4px solid #ffcc00; border-radius: 50%; margin: 0 auto 15px auto; display: flex; justify-content: center; align-items: center; overflow: hidden; }
        .sidebar-logo-container img { width: 80%; height: 80%; object-fit: contain; }
        .insight-box { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; margin-top: 10px; }
        #audit-log { font-size: 11px; color: #cbd5e1; max-height: 250px; overflow-y: auto; }
        .log-item { border-bottom: 1px solid rgba(255,255,255,0.1); padding: 8px 0; }
        #main { margin-left: 280px; padding: 40px; flex-grow: 1; box-sizing: border-box; }
        .admin-box { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #e2e8f0; }
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        input, select, textarea { padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        .ticket-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        .ticket-card { background: white; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .card-img { width: 100%; height: 180px; object-fit: cover; }
        .card-body { padding: 20px; display: flex; flex-direction: column; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-bottom: 12px; align-self: flex-start; }
        .status-available { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .btn { width: 100%; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 8px; }
        .btn-claim { background: var(--dark); color: white; }
        .btn-approve { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-outline { background: #f1f5f9; color: #64748b; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 30px; }
        .page-link { padding: 8px 16px; background: white; border: 1px solid #cbd5e1; text-decoration: none; color: var(--dark); border-radius: 6px; }
        .page-link.active { background: var(--it-blue); color: white; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 450px; }
        .claim-info-box { background: #f8fafc; padding: 20px; border-radius: 12px; margin: 15px 0; border: 1px solid #e2e8f0; border-left: 5px solid var(--warning); }
        .info-label { font-size: 11px; font-weight: bold; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 4px; }
        .info-value { font-size: 15px; color: var(--dark); margin-bottom: 15px; font-weight: 600; }
    </style>
</head>
<body>

<div id="sidebar">
    <div class="sidebar-logo-container"><img src="logo.png"></div>
    <h2 style="text-align: center; margin: 0;">Find IT Lab</h2>
    <p style="text-align: center; font-size: 14px;">User: <strong style="color: #ffcc00;"><?php echo $_SESSION['username']; ?></strong></p>
    <div class="insight-box">
        <small style="font-weight:bold;">SYSTEM AUDIT TRAIL</small>
        <div id="audit-log">
            <?php
            $logs = $pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 10")->fetchAll();
            foreach($logs as $l) { echo "<div class='log-item'>[" . date('H:i', strtotime($l['action_date'])) . "] " . htmlspecialchars($l['action_text']) . "</div>"; }
            ?>
        </div>
    </div>
</div>

<div id="main">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1>Dashboard</h1>
        <button class="btn" style="width: 100px; background:var(--danger); color:white; margin:0;" onclick="location.href='logout.php'">Logout</button>
    </div>

    <form method="GET" style="display:flex; gap:10px; margin-bottom: 25px;">
        <input type="text" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>" style="flex-grow:1;">
        <button type="submit" class="btn" style="margin:0; width:120px; background:var(--it-blue); color:white;">Filter</button>
    </form>

    <?php if ($_SESSION['role'] == 'admin'): ?>
    <div class="admin-box">
        <form action="index.php" method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <input type="text" name="itemName" placeholder="ITEM NAME" required style="flex:1;">
                <select name="labRoom" style="flex:1;"><option>Lab 1</option><option>Lab 2</option></select>
                <input type="file" name="itemImage" accept="image/*" required style="flex:1;">
            </div>
            <textarea name="description" required style="width:100%; margin-bottom:15px;" placeholder="Describe location..."></textarea>
            <button type="submit" class="btn" style="background:var(--it-blue); color:white; width:150px; margin:0;">Broadcast</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="ticket-grid">
        <?php foreach ($items as $row) { ?>
            <div class="ticket-card">
                <img src="<?php echo $row['item_image']; ?>" class="card-img">
                <div class="card-body">
                    <span class="status-badge <?php echo ($row['status'] == 'available') ? 'status-available' : 'status-pending'; ?>">
                        <?php echo strtoupper($row['status']); ?>
                    </span>
                    <div style="display:flex; justify-content:space-between;">
                        <h3 style="margin:0;"><?php echo htmlspecialchars($row['item_name']); ?></h3>
                        <small><?php echo htmlspecialchars($row['lab_room']); ?></small>
                    </div>
                    <p style="color:#64748b; font-size:13px;"><?php echo htmlspecialchars($row['description']); ?></p>

                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <?php if ($row['status'] == 'pending'): ?>
                            <button class="btn btn-approve" onclick="openApproveModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['item_name']); ?>', '<?php echo $row['student_id'] ?? 'N/A'; ?>', '<?php echo addslashes($row['claim_message'] ?? 'No message'); ?>')">View Claim Info</button>
                        <?php else: ?>
                            <button class="btn btn-outline" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['item_name']); ?>', '<?php echo addslashes($row['description']); ?>', '<?php echo $row['lab_room']; ?>')">Edit Details</button>
                        <?php endif; ?>
                        <button class="btn btn-danger" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['item_name']); ?>')">Remove Ticket</button>
                    <?php else: ?>
                        <?php if ($row['status'] == 'pending'): ?>
                             <button class="btn btn-danger" onclick="cancelClaim(<?php echo $row['id']; ?>, '<?php echo addslashes($row['item_name']); ?>')">Cancel Claim</button>
                        <?php else: ?>
                            <button class="btn btn-claim" onclick="openClaimModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['item_name']); ?>')">Claim Item</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
</div>

<div id="editModal" class="modal"><div class="modal-content"><h2>Edit</h2><form action="index.php" method="POST" enctype="multipart/form-data"><input type="hidden" name="editID" id="editID"><input type="text" name="itemName" id="editName" style="width:100%; margin-bottom:10px;"><select name="labRoom" id="editRoom" style="width:100%; margin-bottom:10px;"><option>Lab 1</option><option>Lab 2</option></select><textarea name="description" id="editDesc" style="width:100%; height:80px;"></textarea><input type="file" name="itemImage" style="margin-top:10px;"><button type="submit" class="btn btn-approve">Save</button><button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button></form></div></div>

<div id="claimModal" class="modal">
    <div class="modal-content">
        <h2>Claim Request</h2>
        <form action="index.php" method="POST" id="claimForm">
            <input type="hidden" name="submitClaim" value="1">
            <input type="hidden" name="claimItemID" id="claimItemID">
            <input type="text" name="studentID" id="studentID" placeholder="Student ID Number" required style="width:100%; margin-bottom:10px;">
            <textarea name="studentMsg" id="studentMsg" placeholder="Proof or Message" required style="width:100%; height:100px;"></textarea>
            <button type="submit" class="btn btn-approve">Submit Request</button>
            <button type="button" class="btn btn-outline" onclick="closeModal('claimModal')">Cancel</button>
        </form>
    </div>
</div>

<div id="approveModal" class="modal">
    <div class="modal-content" style="border-top: 8px solid var(--success);">
        <h2 style="margin-top: 0;">Claim Verification</h2>
        <p style="color: #64748b;">Reviewing claim for: <strong id="approveItemName" style="color: var(--dark);"></strong></p>
        <div class="claim-info-box">
            <span class="info-label">Student ID Number</span>
            <div class="info-value" id="claimantID"></div>
            <span class="info-label">Proof / Message</span>
            <div class="info-value" id="claimantMsg" style="font-weight: normal; font-style: italic;"></div>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-approve" style="flex:2;" onclick="executeApprove()">Approve & Release</button>
            <button class="btn btn-outline" style="flex:1;" onclick="closeModal('approveModal')">Back</button>
        </div>
    </div>
</div>

<div id="deleteModal" class="modal"><div class="modal-content"><h2>Action?</h2><p id="deleteItemName"></p><div id="deleteActions"></div><button class="btn btn-outline" onclick="closeModal('deleteModal')">Close</button></div></div>

<script>
    const socket = new WebSocket('ws://localhost:8080');
    let currentId = null, currentName = '';

    function openEditModal(id, name, desc, room) {
        document.getElementById('editID').value = id; document.getElementById('editName').value = name;
        document.getElementById('editDesc').value = desc; document.getElementById('editRoom').value = room;
        document.getElementById('editModal').style.display = 'flex';
    }

    function openClaimModal(id, name) { 
        currentId = id; 
        currentName = name; 
        document.getElementById('claimItemID').value = id; 
        document.getElementById('claimModal').style.display = 'flex'; 
    }

    function openApproveModal(id, name, sid, msg) { 
        currentId = id; currentName = name;
        document.getElementById('approveItemName').innerText = name; 
        document.getElementById('claimantID').innerText = sid;
        document.getElementById('claimantMsg').innerText = msg;
        document.getElementById('approveModal').style.display = 'flex'; 
    }

    function confirmDelete(id, name) { 
        currentId = id; currentName = name; 
        document.getElementById('deleteItemName').innerText = "Delete " + name + "?";
        document.getElementById('deleteActions').innerHTML = '<button class="btn btn-danger" onclick="executeDelete()">Delete Permanently</button>';
        document.getElementById('deleteModal').style.display = 'flex'; 
    }

    function cancelClaim(id, name) {
        currentId = id; currentName = name;
        document.getElementById('deleteItemName').innerText = "Cancel your claim for " + name + "?";
        document.getElementById('deleteActions').innerHTML = '<button class="btn btn-danger" onclick="executeCancelClaim()">Yes, Cancel Claim</button>';
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    // --- BUTTON ACTIONS ---

    // Updated: Redirects to the PHP cancel logic
    function executeCancelClaim() { 
        window.location.href = "index.php?action=cancel_claim&id=" + currentId + "&item_name=" + encodeURIComponent(currentName);
    }

    function executeApprove() { 
        socket.send(JSON.stringify({ type: 'APPROVE_CLAIM', id: currentId, item_name: currentName })); 
        closeModal('approveModal'); 
    }

    function executeDelete() { 
        socket.send(JSON.stringify({ type: 'DELETE_ITEM', id: currentId, item_name: currentName })); 
        closeModal('deleteModal'); 
    }

    socket.onmessage = (e) => { location.reload(); };
</script>
</body>
</html>