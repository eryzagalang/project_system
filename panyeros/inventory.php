<?php
session_start();

// Check if user is logged in.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// --- NEW CACHE-CONTROL HEADERS ---
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
// --- END OF NEW HEADERS ---

?>

<?php
include 'db/db_connect.php';

// Helper function to log history
function logInventoryHistory($conn, $inventoryId, $itemName, $category, $quantity, $unit, $datePurchase, $expirationDate, $stocks, $actionType, $notes = '') {
    $stmt = $conn->prepare("INSERT INTO inventory_history (inventory_id, item_name, category, quantity, unit, date_purchase, expiration_date, stocks, action_type, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('issdssssss', $inventoryId, $itemName, $category, $quantity, $unit, $datePurchase, $expirationDate, $stocks, $actionType, $notes);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle API requests
if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['action'];
    
    function jsonResponse($data) {
        echo json_encode($data);
        exit;
    }

    switch ($action) {
        case 'getInventory':
            $sql = "SELECT * FROM inventory ORDER BY expiration_date ASC";
            $result = $conn->query($sql);
            
            if (!$result) {
                jsonResponse(['success' => false, 'message' => $conn->error]);
            }
            
            $items = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = [
                    'id' => (int)$row['ID'],
                    'item_name' => $row['item_name'],
                    'category' => $row['category'],
                    'quantity' => (float)$row['quantity'],
                    'unit' => $row['unit'],
                    'date_purchase' => $row['date_purchase'],
                    'expiration_date' => $row['expiration_date'],
                    'stocks' => (int)$row['stocks']
                ];
            }
            
            jsonResponse(['success' => true, 'items' => $items]);
            break;

        case 'getHistory':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $sql = "SELECT * FROM inventory_history ORDER BY action_date DESC LIMIT ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = [
                    'id' => (int)$row['ID'],
                    'inventory_id' => $row['inventory_id'],
                    'item_name' => $row['item_name'],
                    'category' => $row['category'],
                    'quantity' => (float)$row['quantity'],
                    'unit' => $row['unit'],
                    'date_purchase' => $row['date_purchase'],
                    'expiration_date' => $row['expiration_date'],
                    'stocks' => (int)$row['stocks'],
                    'action_type' => $row['action_type'],
                    'action_date' => $row['action_date'],
                    'notes' => $row['notes']
                ];
            }
            
            jsonResponse(['success' => true, 'history' => $history]);
            break;

        case 'addItem':
            $itemName = trim($_POST['item_name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $quantity = (float)($_POST['quantity'] ?? 0);
            $unit = trim($_POST['unit'] ?? '');
            $datePurchase = $_POST['date_purchase'] ?? null;
            $expirationDate = $_POST['expiration_date'] ?? null;
            $stocks = (int)($_POST['stocks'] ?? 0);

            if (!$itemName || !$category || !$unit) {
                jsonResponse(['success' => false, 'message' => 'Please fill all required fields']);
            }

            $stmt = $conn->prepare("INSERT INTO inventory (item_name, category, quantity, unit, date_purchase, expiration_date, stocks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if (!$stmt) {
                jsonResponse(['success' => false, 'message' => $conn->error]);
            }

            $stmt->bind_param('ssdsssi', $itemName, $category, $quantity, $unit, $datePurchase, $expirationDate, $stocks);
            
            if ($stmt->execute()) {
                $insertId = $conn->insert_id;
                // Log to history
                logInventoryHistory($conn, $insertId, $itemName, $category, $quantity, $unit, $datePurchase, $expirationDate, $stocks, 'added', 'Item added to inventory');
                jsonResponse(['success' => true, 'message' => 'Item added successfully', 'id' => $insertId]);
            }
            
            jsonResponse(['success' => false, 'message' => $stmt->error]);
            break;

        case 'updateItem':
            $id = (int)($_POST['id'] ?? 0);
            $itemName = trim($_POST['item_name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $quantity = (float)($_POST['quantity'] ?? 0);
            $unit = trim($_POST['unit'] ?? '');
            $datePurchase = $_POST['date_purchase'] ?? null;
            $expirationDate = $_POST['expiration_date'] ?? null;
            $stocks = (int)($_POST['stocks'] ?? 0);

            if (!$id || !$itemName || !$category || !$unit) {
                jsonResponse(['success' => false, 'message' => 'Please fill all required fields']);
            }

            $stmt = $conn->prepare("UPDATE inventory SET item_name=?, category=?, quantity=?, unit=?, date_purchase=?, expiration_date=?, stocks=? WHERE ID=?");
            
            if (!$stmt) {
                jsonResponse(['success' => false, 'message' => $conn->error]);
            }

            $stmt->bind_param('ssdsssii', $itemName, $category, $quantity, $unit, $datePurchase, $expirationDate, $stocks, $id);
            
            if ($stmt->execute()) {
                // Log to history
                logInventoryHistory($conn, $id, $itemName, $category, $quantity, $unit, $datePurchase, $expirationDate, $stocks, 'updated', 'Item details updated');
                jsonResponse(['success' => true, 'message' => 'Item updated successfully']);
            }
            
            jsonResponse(['success' => false, 'message' => $stmt->error]);
            break;

        case 'deleteItem':
            $id = (int)($_POST['id'] ?? 0);
            
            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'Invalid item ID']);
            }

            // Get item details before deletion
            $getStmt = $conn->prepare("SELECT * FROM inventory WHERE ID=?");
            $getStmt->bind_param('i', $id);
            $getStmt->execute();
            $result = $getStmt->get_result();
            $item = $result->fetch_assoc();

            if ($item) {
                // Log to history before deletion
                logInventoryHistory($conn, $id, $item['item_name'], $item['category'], $item['quantity'], $item['unit'], $item['date_purchase'], $item['expiration_date'], $item['stocks'], 'deleted', 'Item removed from inventory');
            }

            $stmt = $conn->prepare("DELETE FROM inventory WHERE ID=?");
            
            if (!$stmt) {
                jsonResponse(['success' => false, 'message' => $conn->error]);
            }

            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                jsonResponse(['success' => true, 'message' => 'Item deleted successfully']);
            }
            
            jsonResponse(['success' => false, 'message' => $stmt->error]);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Panyeros Kusina</title>
    <link rel="stylesheet" href="invent.css">
   
</head>
<style>

    
        .filter-container {
            background: rgba(255,255,255,0.3);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            display: block;
            margin-bottom: 5px;
            color: #2d2d2d;
            font-weight: 600;
            font-size: 13px;
        }

        .filter-input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: rgba(255,255,255,0.5);
        }

        .filter-input:focus {
            outline: none;
            border-color: #638ECB;
            background: #fff;
        }

        .filter-btn {
            padding: 10px 20px;
            background: #638ECB;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.3s;
        }

        .filter-btn:hover {
            background: #4A6FA5;
        }

        .filter-btn.clear {
            background: #6c757d;
        }

        .filter-btn.clear:hover {
            background: #5a6268;
        }

        .active-filters {
            background: rgba(212, 135, 75, 0.1);
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #2d2d2d;
        }

        .filter-tag {
            display: inline-block;
            background:  #638ECB  ;
            color: #fff;
            padding: 4px 10px;
            border-radius: 12px;
            margin-right: 8px;
            font-size: 12px;
        }

        .quick-filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .quick-filter-btn {
            padding: 8px 15px;
            background: rgba(212, 135, 75, 0.2);
            color: #2d2d2d;
            border: 2px solid  #638ECB  ;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .quick-filter-btn:hover {
            background:  #638ECB  ;
            color: #fff;
        }

        .quick-filter-btn.active {
            background:  #638ECB  ;
            color: #fff;
        }

        .print-btn, .copy-btn, .history-btn {
            padding: 10px 20px;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .print-btn {
            background: #28a745;
        }

        .print-btn:hover {
            background: #218838;
        }

        .copy-btn {
            background: #007bff;
        }

        .copy-btn:hover {
            background: #0056b3;
        }

        .history-btn {
            background: #6f42c1;
        }

        .history-btn:hover {
            background: #5a32a3;
        }

        /* Print styles */
        @media print {
            .sidebar,
            .top-bar,
            .filter-container,
            .quick-filter-buttons,
            .search-box,
            .active-filters,
            .action-btn,
            .print-btn,
            .copy-btn,
            .history-btn,
            .stats-bar,
            .nav-tabs {
                display: none !important;
            }

            body {
                background: white;
            }

            .main-content {
                margin-left: 0;
            }

            .container {
                grid-template-columns: 1fr;
            }

            .card {
                box-shadow: none;
                page-break-inside: avoid;
            }

            .card:first-child {
                display: none;
            }

            .card-header {
                background: #333 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color: white !important;
            }

            .inventory-table, .history-table {
                font-size: 10px;
            }

            .inventory-table th,
            .inventory-table td,
            .history-table th,
            .history-table td {
                padding: 6px 4px;
                border: 1px solid #ddd;
            }

            .stock-status {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            @page {
                margin: 1cm;
                size: landscape;
            }

            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 3px solid #333;
            }

            .print-header h1 {
                margin: 0;
                font-size: 28px;
                color: #333;
            }

            .print-header p {
                margin: 5px 0;
                font-size: 14px;
                color: #666;
            }

            .print-summary {
                display: block !important;
                margin-bottom: 20px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-summary h3 {
                margin: 0 0 10px 0;
                color: #333;
                font-size: 16px;
            }

            .print-summary-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
            }

            .print-summary-item {
                padding: 10px;
                background: white;
                border-radius: 4px;
                text-align: center;
                border: 1px solid #ddd;
            }

            .print-summary-value {
                font-size: 18px;
                font-weight: bold;
                color: #D4874B;
            }

            .print-summary-label {
                font-size: 11px;
                color: #666;
                margin-top: 5px;
            }

            .print-category-summary {
                display: block !important;
                margin-bottom: 20px;
                padding: 15px;
                background: #fff;
                border: 2px solid #333;
                border-radius: 8px;
            }

            .print-category-summary h3 {
                margin: 0 0 10px 0;
                color: #333;
                font-size: 16px;
            }

            .category-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }

            .category-item {
                padding: 8px;
                background: #f8f9fa;
                border-radius: 4px;
                border: 1px solid #ddd;
            }

            .category-name {
                font-weight: bold;
                color: #333;
                font-size: 12px;
            }

            .category-count {
                color: #666;
                font-size: 11px;
            }

            .inventory-table tbody tr,
            .history-table tbody tr {
                page-break-inside: avoid;
            }

            .tab-content {
                display: block !important;
            }

            .tab-pane {
                display: block !important;
            }
        }

        .print-header {
            display: none;
        }

        .print-summary {
            display: none;
        }

        .print-category-summary {
            display: none;
        }

        /* Tab Navigation */
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 0;
        }

        .nav-tab {
            padding: 12px 24px;
            background: rgba(255,255,255,0.5);
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: #2d2d2d;
            transition: all 0.3s;
        }

        .nav-tab:hover {
            background: rgba(212, 135, 75, 0.2);
        }

        .nav-tab.active {
            background: rgba(212, 135, 75, 0.3);
            border-bottom-color:  #638ECB  ;
            color:  #638ECB  ;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* History Table Styles */
        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .history-table th {
            background: #2d2d2d;
            color: #fff;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        .history-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }

        .history-table tbody tr:hover {
            background: #f8f9fa;
        }

        .action-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .action-added {
            background: #d4edda;
            color: #155724;
        }

        .action-updated {
            background: #fff3cd;
            color: #856404;
        }

        .action-deleted {
            background: #f8d7da;
            color: #721c24;
        }
    
</style>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            Panyeros kusina
        </div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="home.php">
                    <span class="nav-icon">🏠</span>
                    <span>Home</span>
                </a>
            </li>
            <li class="nav-item active">
                <a href="inventory.php">
                    <span class="nav-icon">📦</span>
                    <span>Inventory</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="loyalty.php">
                    <span class="nav-icon">💳</span>
                    <span>Loyalty Cards</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="feedback.php">
                    <span class="nav-icon">💬</span>
                    <span>Feedbacks</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-header-title">
                📦 Inventory Management
            </h1>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </div>
        
        <div class="content-area">
            <div id="expirationNotification" style="display: none;"></div>

            <div class="stats-bar">
                <div class="stat-box">
                    <div class="stat-value" id="totalItems">0</div>
                    <div class="stat-label">Total Items</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" id="lowStockCount">0</div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" id="expiringCount">0</div>
                    <div class="stat-label">Expiring Soon (7 days)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" id="totalValue">0</div>
                    <div class="stat-label">Total Quantity</div>
                </div>
            </div>
            
            <div class="container">
                <div class="card">
                    <div class="card-header">
                        ➕ Add New Item
                    </div>
                    <div class="card-body">
                        <div id="alertContainer"></div>
                        <form id="inventoryForm">
                            <div class="form-group">
                                <label class="form-label">Item Name</label>
                                <input type="text" id="itemName" class="form-control" 
                                       placeholder="e.g., Rice" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select id="category" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <option value="grains">Grains</option>
                                    <option value="meat">Meat</option>
                                    <option value="vegetables">Vegetables</option>
                                    <option value="condiments">Condiments</option>
                                    <option value="dairy">Dairy</option>
                                    <option value="others">Others</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Quantity</label>
                                <input type="number" id="quantity" class="form-control" 
                                       placeholder="e.g., 50" min="0" step="0.01" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Unit</label>
                                <input type="text" id="unit" class="form-control" 
                                       placeholder="e.g., kg, L, pcs" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" id="purchaseDate" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Expiration Date</label>
                                <input type="date" id="expiryDate" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Low Stock Threshold</label>
                                <input type="number" id="threshold" class="form-control" 
                                       placeholder="e.g., 10" min="0" value="0" required>
                            </div>
                            
                            <button type="submit" class="submit-btn">Add Item</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        📋 Inventory Management
                    </div>
                    <div class="card-body">
                        <!-- Tab Navigation -->
                        <div class="nav-tabs">
                            <button class="nav-tab active" onclick="switchTab('current')">
                                📦 Current Inventory
                            </button>
                            <button class="nav-tab" onclick="switchTab('history')">
                                📜 History
                            </button>
                        </div>

                        <!-- Current Inventory Tab -->
                        <div id="currentTab" class="tab-content active">
                            <!-- Quick Filter Buttons -->
                            <div class="quick-filter-buttons">
                                <button class="quick-filter-btn" onclick="applyQuickFilter('expired')">⚠️ Expired Items</button>
                                <button class="quick-filter-btn" onclick="applyQuickFilter('expiring')">📅 Expiring (7 days)</button>
                                <button class="quick-filter-btn" onclick="applyQuickFilter('month')">📆 Expiring This Month</button>
                                <button class="quick-filter-btn" onclick="applyQuickFilter('all')">📋 All Items</button>
                                <button class="print-btn" onclick="printInventory()">🖨️ Print</button>
                                <button class="copy-btn" onclick="copyInventoryData()">📋 Copy Data</button>
                            </div>

                            <!-- Date Filter Section -->
                            <div class="filter-container">
                                <div class="filter-group">
                                    <label class="filter-label">Start Date (Expiry)</label>
                                    <input type="date" id="startDate" class="filter-input">
                                </div>
                                <div class="filter-group">
                                    <label class="filter-label">End Date (Expiry)</label>
                                    <input type="date" id="endDate" class="filter-input">
                                </div>
                                <div class="filter-group" style="flex: 0 0 auto;">
                                    <button class="filter-btn" onclick="applyDateFilter()">🔍 Filter</button>
                                </div>
                                <div class="filter-group" style="flex: 0 0 auto;">
                                    <button class="filter-btn clear" onclick="clearDateFilter()">✖ Clear</button>
                                </div>
                            </div>

                            <!-- Active Filters Display -->
                            <div id="activeFilters" style="display: none;" class="active-filters"></div>

                            <!-- Search Box -->
                            <div class="search-box">
                                <input type="text" id="searchInput" class="search-input" 
                                       placeholder="🔍 Search items by name or category...">
                            </div>

                            <!-- Print Header (only visible when printing) -->
                            <div class="print-header">
                                <h1>📦 Panyeros Kusina - Inventory Report</h1>
                                <p id="printDate"></p>
                                <p id="printFilterInfo"></p>
                            </div>

                            <!-- Print Summary (only visible when printing) -->
                            <div class="print-summary">
                                <h3>📊 Inventory Summary</h3>
                                <div class="print-summary-grid">
                                    <div class="print-summary-item">
                                        <div class="print-summary-value" id="printTotalItems">0</div>
                                        <div class="print-summary-label">Total Items</div>
                                    </div>
                                    <div class="print-summary-item">
                                        <div class="print-summary-value" id="printLowStock">0</div>
                                        <div class="print-summary-label">Low Stock Items</div>
                                    </div>
                                    <div class="print-summary-item">
                                        <div class="print-summary-value" id="printExpiring">0</div>
                                        <div class="print-summary-label">Expiring Soon</div>
                                    </div>
                                    <div class="print-summary-item">
                                        <div class="print-summary-value" id="printTotalQty">0</div>
                                        <div class="print-summary-label">Total Quantity</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Category Summary (only visible when printing) -->
                            <div class="print-category-summary">
                                <h3>📂 Items by Category</h3>
                                <div class="category-grid" id="printCategoryGrid"></div>
                            </div>

                            <div class="table-container">
                                <table class="inventory-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Item Name</th>
                                            <th>Category</th>
                                            <th>Quantity</th>
                                            <th>Unit</th>
                                            <th>Purchase Date</th>
                                            <th>Expiry Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="inventoryTableBody">
                                        <tr><td colspan="9" style="text-align:center;padding:30px;color:#999;">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- History Tab -->
                        <div id="historyTab" class="tab-content">
                            <div class="quick-filter-buttons">
                                <button class="history-btn" onclick="printHistory()">🖨️ Print History</button>
                                <button class="copy-btn" onclick="copyHistoryData()">📋 Copy History</button>
                                <select id="historyLimit" class="filter-input" style="width: auto;" onchange="loadHistory()">
                                    <option value="50">Last 50 records</option>
                                    <option value="100" selected>Last 100 records</option>
                                    <option value="200">Last 200 records</option>
                                    <option value="500">Last 500 records</option>
                                </select>
                            </div>

                            <div class="table-container">
                                <table class="history-table">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Action</th>
                                            <th>Item Name</th>
                                            <th>Category</th>
                                            <th>Quantity</th>
                                            <th>Unit</th>
                                            <th>Purchase Date</th>
                                            <th>Expiry Date</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <tr><td colspan="9" style="text-align:center;padding:30px;color:#999;">Loading history...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
            <div class="modal-header">✏️ Edit Item</div>
            <form id="editForm">
                <input type="hidden" id="edit_id">
                <div class="form-group">
                    <label class="form-label">Item Name</label>
                    <input type="text" id="edit_item_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select id="edit_category" class="form-control" required>
                        <option value="grains">Grains</option>
                        <option value="meat">Meat</option>
                        <option value="vegetables">Vegetables</option>
                        <option value="condiments">Condiments</option>
                        <option value="dairy">Dairy</option>
                        <option value="others">Others</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Quantity</label>
                    <input type="number" id="edit_quantity" class="form-control" min="0" step="0.01" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Unit</label>
                    <input type="text" id="edit_unit" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" id="edit_date_purchase" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Expiration Date</label>
                    <input type="date" id="edit_expiration_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Stock Threshold</label>
                    <input type="number" id="edit_stocks" class="form-control" min="0">
                </div>
                
                <button type="submit" class="submit-btn" style="margin-bottom: 10px;">Update Item</button>
                <button type="button" class="submit-btn" style="background: #6c757d;" onclick="closeEditModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        let inventoryData = [];
        let historyData = [];
        let currentFilters = {
            startDate: null,
            endDate: null,
            searchText: ''
        };
        let currentTab = 'current';

        // Set today's date as default
        document.getElementById('purchaseDate').value = new Date().toISOString().split('T')[0];

        // Load inventory on page load
        loadInventory();
        loadHistory();

        // Tab switching
        function switchTab(tab) {
            currentTab = tab;
            
            // Update tab buttons
            document.querySelectorAll('.nav-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            if (tab === 'current') {
                document.getElementById('currentTab').classList.add('active');
            } else {
                document.getElementById('historyTab').classList.add('active');
                loadHistory(); // Refresh history when switching to tab
            }
        }

        // Add item form submission
        document.getElementById('inventoryForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'addItem');
            formData.append('item_name', document.getElementById('itemName').value);
            formData.append('category', document.getElementById('category').value);
            formData.append('quantity', document.getElementById('quantity').value);
            formData.append('unit', document.getElementById('unit').value);
            formData.append('date_purchase', document.getElementById('purchaseDate').value);
            formData.append('expiration_date', document.getElementById('expiryDate').value);
            formData.append('stocks', document.getElementById('threshold').value);

            try {
                const response = await fetch('inventory.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('✅ ' + data.message, 'success');
                    document.getElementById('inventoryForm').reset();
                    document.getElementById('purchaseDate').value = new Date().toISOString().split('T')[0];
                    loadInventory();
                    loadHistory();
                } else {
                    showAlert('❌ ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error: ' + error.message, 'error');
            }
        });

        // Edit form submission
        document.getElementById('editForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'updateItem');
            formData.append('id', document.getElementById('edit_id').value);
            formData.append('item_name', document.getElementById('edit_item_name').value);
            formData.append('category', document.getElementById('edit_category').value);
            formData.append('quantity', document.getElementById('edit_quantity').value);
            formData.append('unit', document.getElementById('edit_unit').value);
            formData.append('date_purchase', document.getElementById('edit_date_purchase').value);
            formData.append('expiration_date', document.getElementById('edit_expiration_date').value);
            formData.append('stocks', document.getElementById('edit_stocks').value);

            try {
                const response = await fetch('inventory.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('✅ ' + data.message, 'success');
                    closeEditModal();
                    loadInventory();
                    loadHistory();
                } else {
                    showAlert('❌ ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error: ' + error.message, 'error');
            }
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', (e) => {
            currentFilters.searchText = e.target.value.toLowerCase();
            renderTable();
        });

        // Quick filter functions
        function applyQuickFilter(type) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            // Remove active class from all buttons
            document.querySelectorAll('.quick-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            switch(type) {
                case 'expired':
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    document.getElementById('startDate').value = '';
                    document.getElementById('endDate').value = yesterday.toISOString().split('T')[0];
                    event.target.classList.add('active');
                    break;
                    
                case 'expiring':
                    const sevenDays = new Date(today);
                    sevenDays.setDate(sevenDays.getDate() + 7);
                    document.getElementById('startDate').value = today.toISOString().split('T')[0];
                    document.getElementById('endDate').value = sevenDays.toISOString().split('T')[0];
                    event.target.classList.add('active');
                    break;
                    
                case 'month':
                    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];
                    document.getElementById('endDate').value = lastDay.toISOString().split('T')[0];
                    event.target.classList.add('active');
                    break;
                    
                case 'all':
                    document.getElementById('startDate').value = '';
                    document.getElementById('endDate').value = '';
                    event.target.classList.add('active');
                    break;
            }
            
            applyDateFilter();
        }

        // Date filter functions
        function applyDateFilter() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            if (startDate && endDate && startDate > endDate) {
                showAlert('⚠️ Start date cannot be after end date', 'error');
                return;
            }

            currentFilters.startDate = startDate;
            currentFilters.endDate = endDate;

            updateActiveFiltersDisplay();
            renderTable();
        }

        function clearDateFilter() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            currentFilters.startDate = null;
            currentFilters.endDate = null;
            
            document.querySelectorAll('.quick-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            updateActiveFiltersDisplay();
            renderTable();
        }

        function updateActiveFiltersDisplay() {
            const activeFiltersDiv = document.getElementById('activeFilters');
            
            if (currentFilters.startDate || currentFilters.endDate) {
                let filterText = '📅 Active Filters: ';
                const tags = [];
                
                if (currentFilters.startDate) {
                    tags.push(`<span class="filter-tag">From: ${currentFilters.startDate}</span>`);
                }
                if (currentFilters.endDate) {
                    tags.push(`<span class="filter-tag">To: ${currentFilters.endDate}</span>`);
                }
                
                activeFiltersDiv.innerHTML = filterText + tags.join('');
                activeFiltersDiv.style.display = 'block';
            } else {
                activeFiltersDiv.style.display = 'none';
            }
        }

        async function loadInventory() {
            try {
                const response = await fetch('inventory.php?action=getInventory');
                const data = await response.json();
                
                if (data.success) {
                    inventoryData = data.items;
                    updateStats();
                    renderTable();
                } else {
                    showAlert('❌ Error loading inventory: ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error connecting to database: ' + error.message, 'error');
            }
        }

        async function loadHistory() {
            try {
                const limit = document.getElementById('historyLimit').value;
                const response = await fetch(`inventory.php?action=getHistory&limit=${limit}`);
                const data = await response.json();
                
                if (data.success) {
                    historyData = data.history;
                    renderHistoryTable();
                } else {
                    showAlert('❌ Error loading history: ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error loading history: ' + error.message, 'error');
            }
        }

        function updateStats() {
            const totalItems = inventoryData.length;
            let lowStockCount = 0;
            let expiringCount = 0;
            let totalQuantity = 0;

            const sevenDaysFromNow = new Date();
            sevenDaysFromNow.setDate(sevenDaysFromNow.getDate() + 7);
            const today = new Date();

            inventoryData.forEach(item => {
                totalQuantity += item.quantity;
                
                if (item.quantity <= item.stocks) {
                    lowStockCount++;
                }

                const expiryDate = new Date(item.expiration_date);
                if (expiryDate <= sevenDaysFromNow && expiryDate >= today) {
                    expiringCount++;
                }
            });
            
            document.getElementById('totalItems').textContent = totalItems;
            document.getElementById('lowStockCount').textContent = lowStockCount;
            document.getElementById('expiringCount').textContent = expiringCount;
            document.getElementById('totalValue').textContent = totalQuantity.toFixed(2);
        }

        function getExpiryStatus(expiryDate) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const expiry = new Date(expiryDate);
            expiry.setHours(0, 0, 0, 0);
            
            const daysUntilExpiry = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24));
            
            if (daysUntilExpiry < 0) {
                return '<span class="stock-status stock-expired">EXPIRED</span>';
            } else if (daysUntilExpiry <= 7) {
                return '<span class="stock-status stock-expiring">' + daysUntilExpiry + ' days left</span>';
            } else {
                return '<span class="stock-status stock-good">' + daysUntilExpiry + ' days</span>';
            }
        }

        function getStockStatus(item) {
            if (item.quantity <= item.stocks) {
                return '<span class="stock-status stock-low">Low Stock</span>';
            } else {
                return '<span class="stock-status stock-good">Good</span>';
            }
        }

        function renderTable() {
            const tbody = document.getElementById('inventoryTableBody');
            
            // Apply all filters
            let filteredItems = inventoryData.filter(item => {
                const matchesSearch = !currentFilters.searchText || 
                    item.item_name.toLowerCase().includes(currentFilters.searchText) ||
                    item.category.toLowerCase().includes(currentFilters.searchText);

                let matchesDateRange = true;
                if (currentFilters.startDate || currentFilters.endDate) {
                    const expiryDate = new Date(item.expiration_date);
                    expiryDate.setHours(0, 0, 0, 0);
                    
                    if (currentFilters.startDate) {
                        const startDate = new Date(currentFilters.startDate);
                        startDate.setHours(0, 0, 0, 0);
                        matchesDateRange = matchesDateRange && expiryDate >= startDate;
                    }
                    
                    if (currentFilters.endDate) {
                        const endDate = new Date(currentFilters.endDate);
                        endDate.setHours(23, 59, 59, 999);
                        matchesDateRange = matchesDateRange && expiryDate <= endDate;
                    }
                }

                return matchesSearch && matchesDateRange;
            });

            filteredItems.sort((a, b) => {
                const dateA = new Date(a.expiration_date);
                const dateB = new Date(b.expiration_date);
                return dateA - dateB;
            });
            
            if (filteredItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:30px;color:#999;">No items found matching the filter criteria</td></tr>';
                return;
            }
            
            const html = filteredItems.map(item => `
                <tr>
                    <td>${item.id}</td>
                    <td><strong>${item.item_name}</strong></td>
                    <td>${item.category}</td>
                    <td>${item.quantity}</td>
                    <td>${item.unit}</td>
                    <td>${item.date_purchase}</td>
                    <td>${item.expiration_date}</td>
                    <td>
                        ${getExpiryStatus(item.expiration_date)}
                        ${getStockStatus(item)}
                    </td>
                    <td>
                        <button class="action-btn" onclick="openEditModal(${item.id})">Edit</button>
                        <button class="action-btn delete" onclick="deleteItem(${item.id}, '${item.item_name.replace(/'/g, "\\'")}')">Delete</button>
                    </td>
                </tr>
            `).join('');
            
            tbody.innerHTML = html;
        }

        function renderHistoryTable() {
            const tbody = document.getElementById('historyTableBody');
            
            if (historyData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:30px;color:#999;">No history records found</td></tr>';
                return;
            }
            
            const html = historyData.map(record => {
                const actionClass = `action-${record.action_type}`;
                const actionText = record.action_type.charAt(0).toUpperCase() + record.action_type.slice(1);
                const date = new Date(record.action_date);
                const formattedDate = date.toLocaleString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric', 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
                
                return `
                    <tr>
                        <td>${formattedDate}</td>
                        <td><span class="action-badge ${actionClass}">${actionText}</span></td>
                        <td><strong>${record.item_name}</strong></td>
                        <td>${record.category}</td>
                        <td>${record.quantity}</td>
                        <td>${record.unit}</td>
                        <td>${record.date_purchase}</td>
                        <td>${record.expiration_date}</td>
                        <td>${record.notes || '-'}</td>
                    </tr>
                `;
            }).join('');
            
            tbody.innerHTML = html;
        }

        function openEditModal(id) {
            const item = inventoryData.find(i => i.id === id);
            if (!item) return;

            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_item_name').value = item.item_name;
            document.getElementById('edit_category').value = item.category;
            document.getElementById('edit_quantity').value = item.quantity;
            document.getElementById('edit_unit').value = item.unit;
            document.getElementById('edit_date_purchase').value = item.date_purchase;
            document.getElementById('edit_expiration_date').value = item.expiration_date;
            document.getElementById('edit_stocks').value = item.stocks;

            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        async function deleteItem(id, name) {
            if (!confirm(`Are you sure you want to delete "${name}"?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'deleteItem');
            formData.append('id', id);

            try {
                const response = await fetch('inventory.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('✅ ' + data.message, 'success');
                    loadInventory();
                    loadHistory();
                } else {
                    showAlert('❌ ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error: ' + error.message, 'error');
            }
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            
            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        // Print functions
        function printInventory() {
            // Update print header with current date
            const today = new Date();
            document.getElementById('printDate').textContent = `Generated: ${today.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            })}`;
            
            // Update filter info
            let filterInfo = 'Showing: ';
            if (currentFilters.startDate || currentFilters.endDate) {
                if (currentFilters.startDate && currentFilters.endDate) {
                    filterInfo += `Items expiring between ${currentFilters.startDate} and ${currentFilters.endDate}`;
                } else if (currentFilters.startDate) {
                    filterInfo += `Items expiring from ${currentFilters.startDate}`;
                } else {
                    filterInfo += `Items expiring until ${currentFilters.endDate}`;
                }
            } else {
                filterInfo += 'All inventory items';
            }
            document.getElementById('printFilterInfo').textContent = filterInfo;
            
            // Update print summary
            document.getElementById('printTotalItems').textContent = document.getElementById('totalItems').textContent;
            document.getElementById('printLowStock').textContent = document.getElementById('lowStockCount').textContent;
            document.getElementById('printExpiring').textContent = document.getElementById('expiringCount').textContent;
            document.getElementById('printTotalQty').textContent = document.getElementById('totalValue').textContent;
            
            // Generate category summary
            const categoryCount = {};
            inventoryData.forEach(item => {
                categoryCount[item.category] = (categoryCount[item.category] || 0) + 1;
            });
            
            const categoryHTML = Object.entries(categoryCount).map(([category, count]) => `
                <div class="category-item">
                    <div class="category-name">${category.charAt(0).toUpperCase() + category.slice(1)}</div>
                    <div class="category-count">${count} items</div>
                </div>
            `).join('');
            
            document.getElementById('printCategoryGrid').innerHTML = categoryHTML;
            
            window.print();
        }

        function printHistory() {
            window.print();
        }

        function copyInventoryData() {
            let text = 'INVENTORY REPORT\n';
            text += '='.repeat(100) + '\n\n';
            text += `Generated: ${new Date().toLocaleString()}\n\n`;
            
            text += 'SUMMARY\n';
            text += '-'.repeat(100) + '\n';
            text += `Total Items: ${inventoryData.length}\n`;
            text += `Low Stock Items: ${document.getElementById('lowStockCount').textContent}\n`;
            text += `Expiring Soon: ${document.getElementById('expiringCount').textContent}\n`;
            text += `Total Quantity: ${document.getElementById('totalValue').textContent}\n\n`;
            
            text += 'INVENTORY ITEMS\n';
            text += '-'.repeat(100) + '\n';
            text += 'ID\tItem Name\tCategory\tQuantity\tUnit\tPurchase Date\tExpiry Date\n';
            text += '-'.repeat(100) + '\n';
            
            inventoryData.forEach(item => {
                text += `${item.id}\t${item.item_name}\t${item.category}\t${item.quantity}\t${item.unit}\t${item.date_purchase}\t${item.expiration_date}\n`;
            });
            
            navigator.clipboard.writeText(text).then(() => {
                showAlert('✅ Inventory data copied to clipboard!', 'success');
            }).catch(err => {
                showAlert('❌ Failed to copy data', 'error');
            });
        }

        function copyHistoryData() {
            let text = 'INVENTORY HISTORY\n';
            text += '='.repeat(100) + '\n\n';
            text += `Generated: ${new Date().toLocaleString()}\n\n`;
            
            text += 'Date & Time\tAction\tItem Name\tCategory\tQuantity\tUnit\tPurchase Date\tExpiry Date\tNotes\n';
            text += '-'.repeat(100) + '\n';
            
            historyData.forEach(record => {
                const date = new Date(record.action_date).toLocaleString();
                text += `${date}\t${record.action_type}\t${record.item_name}\t${record.category}\t${record.quantity}\t${record.unit}\t${record.date_purchase}\t${record.expiration_date}\t${record.notes || '-'}\n`;
            });
            
            navigator.clipboard.writeText(text).then(() => {
                showAlert('✅ History data copied to clipboard!', 'success');
            }).catch(err => {
                showAlert('❌ Failed to copy data', 'error');
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            const modal = document.getElementById('editModal');
            if (e.target === modal) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>