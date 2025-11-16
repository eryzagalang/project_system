<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'panyeros');

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]));
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
        case 'getCustomers':
            $stmt = $conn->query("SELECT * FROM loyalty ORDER BY points DESC");
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($customers as $row) {
                $result[] = [
                    'id' => (int)$row['ID'],
                    'name' => $row['name'],
                    'contact' => $row['Contact'],
                    'points' => (int)$row['points'],
                    'voucher_status' => $row['voucher_status'],
                    'expdate' => $row['expdate'],
                    'timestamp' => $row['timestamp']
                ];
            }
            
            jsonResponse(['success' => true, 'customers' => $result]);
            break;

        case 'addTransaction':
            $name = trim($_POST['name'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $purchaseAmount = (float)($_POST['purchase_amount'] ?? 0);
            $note = trim($_POST['note'] ?? '');

            if (!$name || !$contact || $purchaseAmount <= 0) {
                jsonResponse(['success' => false, 'message' => 'Please fill all required fields']);
            }

            $earnedPoints = floor($purchaseAmount / 50);

            if ($earnedPoints < 1) {
                jsonResponse(['success' => false, 'message' => 'Minimum purchase of ₱50 required']);
            }

            $stmt = $conn->prepare("SELECT ID, points FROM loyalty WHERE Contact = ?");
            $stmt->execute([$contact]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $timestamp = date('Y-m-d H:i:s');
            $expdate = date('Y-m-d', strtotime('+3 months'));
            $transactionNote = $note ? $note : "Purchase: ₱{$purchaseAmount} = {$earnedPoints} pts";
            
            if ($existing) {
                $newPoints = (int)$existing['points'] + $earnedPoints;
                $stmt = $conn->prepare("UPDATE loyalty SET name=?, points=?, voucher_status=?, expdate=?, timestamp=? WHERE ID=?");
                $stmt->execute([$name, $newPoints, $transactionNote, $expdate, $timestamp, $existing['ID']]);
                
                $voucher = null;
                if ($newPoints >= 150) $voucher = '20% discount voucher available!';
                elseif ($newPoints >= 50) $voucher = '10% discount voucher available!';
                
                jsonResponse([
                    'success' => true, 
                    'message' => "Purchase earned {$earnedPoints} points!",
                    'earned_points' => $earnedPoints,
                    'total_points' => $newPoints,
                    'voucher' => $voucher
                ]);
            } else {
                $stmt = $conn->prepare("INSERT INTO loyalty (name, Contact, points, voucher_status, expdate, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $contact, $earnedPoints, $transactionNote, $expdate, $timestamp]);
                
                $voucher = null;
                if ($earnedPoints >= 150) $voucher = '20% discount voucher available!';
                elseif ($earnedPoints >= 50) $voucher = '10% discount voucher available!';
                
                jsonResponse([
                    'success' => true, 
                    'message' => "New customer! Earned {$earnedPoints} points!",
                    'earned_points' => $earnedPoints,
                    'total_points' => $earnedPoints,
                    'voucher' => $voucher
                ]);
            }
            break;

        case 'redeemVoucher':
            $id = (int)($_POST['id'] ?? 0);
            $voucherType = $_POST['voucher_type'] ?? '';
            
            if (!$id) jsonResponse(['success' => false, 'message' => 'Invalid ID']);

            $stmt = $conn->prepare("SELECT name, points FROM loyalty WHERE ID=?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer) {
                $currentPoints = (int)$customer['points'];
                
                if ($voucherType === '10%' && $currentPoints >= 50) {
                    $stmt = $conn->prepare("UPDATE loyalty SET points=0, voucher_status='Redeemed 10% voucher', timestamp=NOW() WHERE ID=?");
                    $stmt->execute([$id]);
                    jsonResponse(['success' => true, 'message' => "🎉 {$customer['name']} redeemed 10% voucher!"]);
                } elseif ($voucherType === '20%' && $currentPoints >= 150) {
                    $stmt = $conn->prepare("UPDATE loyalty SET points=0, voucher_status='Redeemed 20% voucher', timestamp=NOW() WHERE ID=?");
                    $stmt->execute([$id]);
                    jsonResponse(['success' => true, 'message' => "🎉 {$customer['name']} redeemed 20% voucher!"]);
                } else {
                    jsonResponse(['success' => false, 'message' => 'Insufficient points']);
                }
            }
            
            jsonResponse(['success' => false, 'message' => 'Customer not found']);
            break;

        case 'deleteCustomer':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Invalid ID']);

            $stmt = $conn->prepare("DELETE FROM loyalty WHERE ID=?");
            $stmt->execute([$id]);
            jsonResponse(['success' => true, 'message' => 'Customer deleted']);
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
    <title>Loyalty Program - Panyeros Kusina</title>
    <link rel="stylesheet" href="invent.css">
    <style>
        /* Override specific colors to match inventory design */
        .quick-filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .quick-filter-btn {
            padding: 8px 15px;
            background: rgba(99, 125, 203, 0.2);
            color: #2d2d2d;
            border: 2px solid #638ECB;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .quick-filter-btn:hover {
            background: #638ECB;
            color: #fff;
        }

        .quick-filter-btn.active {
            background: #638ECB;
            color: #fff;
        }

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

        .print-btn, .copy-btn {
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

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            position: relative;
        }

        .modal-header {
            background: #638ECB;
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            font-size: 20px;
            font-weight: 600;
        }

        .modal-close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            color: white;
            cursor: pointer;
            line-height: 1;
        }

        .modal-close:hover {
            color: #f0f0f0;
        }

        .customer-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .customer-table th {
            background: #2d2d2d;
            color: #fff;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        .customer-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }

        .customer-table tbody tr:hover {
            background: #f8f9fa;
        }

        .points-badge {
            background: #638ECB;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 12px;
            display: inline-block;
        }

        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* Update calculated points input color */
        #calculatedPoints {
            background: rgba(99, 125, 203, 0.1) !important;
            font-weight: bold;
            color: #638ECB !important;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">Panyeros kusina</div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="home.php"><span class="nav-icon">🏠</span><span>Home</span></a></li>
            <li class="nav-item"><a href="inventory.php"><span class="nav-icon">📦</span><span>Inventory</span></a></li>
            <li class="nav-item active"><a href="loyalty.php"><span class="nav-icon">💳</span><span>Loyalty Cards</span></a></li>
            <li class="nav-item"><a href="feedback.php"><span class="nav-icon">💬</span><span>Feedbacks</span></a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-header-title">💳 Loyalty Program</h1>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </div>
        
        <div class="content-area">
            <div class="stats-bar">
                <div class="stat-box">
                    <div class="stat-value" id="totalCustomers">0</div>
                    <div class="stat-label">Total Customers</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" id="totalPoints">0</div>
                    <div class="stat-label">Total Points Issued</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" id="avgPoints">0</div>
                    <div class="stat-label">Average Points</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" id="expiringCount">0</div>
                    <div class="stat-label">Expiring Soon (30 days)</div>
                </div>
            </div>
            
            <div class="container">
                <div class="card">
                    <div class="card-header">➕ Add Customer Purchase</div>
                    <div class="card-body">
                        <div id="alertContainer"></div>
                        <div style="background: rgba(255,193,7,0.2); padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                            <strong>💰 Points System:</strong><br>
                            • Every ₱50 purchase = 1 point<br>
                            • 50 points = 10% discount voucher<br>
                            • 150 points = 20% discount voucher<br>
                            • Points expire after 3 months<br>
                            • Points reset to 0 after redemption
                        </div>
                        <form id="loyaltyForm">
                            <div class="form-group">
                                <label class="form-label">Customer Name</label>
                                <input type="text" id="customerName" class="form-control" placeholder="Enter customer name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email / Phone</label>
                                <input type="text" id="contact" class="form-control" placeholder="email@example.com or 09171234567" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Purchase Amount (₱)</label>
                                <input type="number" id="purchaseAmount" class="form-control" placeholder="e.g., 250" min="50" step="0.01" required>
                                <div class="help-text">💡 Minimum ₱50 to earn points</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Calculated Points</label>
                                <input type="text" id="calculatedPoints" class="form-control" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Transaction Note (Optional)</label>
                                <textarea id="note" class="form-control" placeholder="Add a note..."></textarea>
                            </div>
                            <button type="submit" class="submit-btn">Submit Purchase</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">👥 Customer List</div>
                    <div class="card-body">
                        <!-- Quick Filter Buttons -->
                        <div class="quick-filter-buttons">
                            <button class="quick-filter-btn active" onclick="applyQuickFilter('all')">📋 All Customers</button>
                            <button class="quick-filter-btn" onclick="applyQuickFilter('voucher-ready')">🎁 Voucher Ready</button>
                            <button class="quick-filter-btn" onclick="applyQuickFilter('high-points')">⭐ High Points (150+)</button>
                            <button class="quick-filter-btn" onclick="applyQuickFilter('expiring')">⏰ Expiring Soon</button>
                            <button class="print-btn" onclick="printCustomers()">🖨️ Print</button>
                            <button class="copy-btn" onclick="copyCustomerData()">📋 Copy Data</button>
                        </div>

                        <!-- Filter Container -->
                        <div class="filter-container">
                            <div class="filter-group">
                                <label class="filter-label">Sort By</label>
                                <select id="sortFilter" class="filter-input">
                                    <option value="points-desc">Highest Points</option>
                                    <option value="points-asc">Lowest Points</option>
                                    <option value="name-asc">Name (A-Z)</option>
                                    <option value="expdate-asc">Expiring Soon</option>
                                    <option value="timestamp-desc">Recent Activity</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Points Filter</label>
                                <select id="pointsFilter" class="filter-input">
                                    <option value="all">All Points</option>
                                    <option value="voucher-ready">Voucher Ready (50+)</option>
                                    <option value="high">High Points (150+)</option>
                                    <option value="medium">Medium (50-149)</option>
                                    <option value="low">Low (1-49)</option>
                                    <option value="zero">Zero Points</option>
                                </select>
                            </div>
                            <div class="filter-group" style="flex: 0 0 auto;">
                                <button class="filter-btn clear" onclick="clearFilters()">✖ Clear</button>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div class="search-box">
                            <input type="text" id="searchInput" class="search-input" placeholder="🔍 Search customers by name or contact...">
                        </div>

                        <div class="table-container">
                            <table class="customer-table">
                                <thead>
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Contact</th>
                                        <th>Points</th>
                                        <th>Voucher Status</th>
                                        <th>Expiry Date</th>
                                        <th>Last Activity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="customerTableBody">
                                    <tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="voucherModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeVoucherModal()">&times;</span>
            <div class="modal-header" id="voucherModalHeader"></div>
            <div id="voucherModalBody" style="padding: 20px; text-align: center;"></div>
        </div>
    </div>

    <script>
        let customers = [];
        let currentSort = 'points-desc';
        let currentPointsFilter = 'all';
        let currentQuickFilter = 'all';

        document.getElementById('purchaseAmount').addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            const points = Math.floor(amount / 50);
            document.getElementById('calculatedPoints').value = points > 0 ? `${points} points (₱${amount})` : '0 points';
        });

        async function loadCustomers() {
            try {
                const response = await fetch('loyalty.php?action=getCustomers');
                const data = await response.json();
                
                if (data.success) {
                    customers = data.customers;
                    updateStats();
                    renderCustomerTable();
                } else {
                    showAlert('❌ Error: ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error loading customers: ' + error.message, 'error');
            }
        }

        function updateStats() {
            const customerCount = customers.length;
            const totalPoints = customers.reduce((sum, c) => sum + c.points, 0);
            const avgPoints = customerCount > 0 ? (totalPoints / customerCount).toFixed(1) : 0;
            const today = new Date();
            const thirtyDaysLater = new Date(today.getTime() + (30 * 24 * 60 * 60 * 1000));
            const expiringCount = customers.filter(c => {
                if (!c.expdate || c.expdate === '0000-00-00') return false;
                const expDate = new Date(c.expdate);
                return expDate >= today && expDate <= thirtyDaysLater && c.points > 0;
            }).length;
            document.getElementById('totalCustomers').textContent = customerCount;
            document.getElementById('totalPoints').textContent = totalPoints.toLocaleString();
            document.getElementById('avgPoints').textContent = avgPoints;
            document.getElementById('expiringCount').textContent = expiringCount;
        }

        function applyQuickFilter(type) {
            currentQuickFilter = type;
            
            document.querySelectorAll('.quick-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            renderCustomerTable();
        }

        function getVoucherStatus(points) {
            if (points >= 150) return '<span style="background:#28a745;color:#fff;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">🎉 20% Voucher Ready!</span>';
            if (points >= 50) return '<span style="background:#ffc107;color:#000;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">🎁 10% Voucher Ready!</span>';
            const needed = 50 - points;
            return `<span style="color:#666;font-size:12px;">${needed} pts to voucher</span>`;
        }

        function formatExpiryDate(expdate) {
            if (!expdate || expdate === '0000-00-00') return '<span style="color:#999;">N/A</span>';
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const expDate = new Date(expdate);
            expDate.setHours(0, 0, 0, 0);
            const daysUntilExpiry = Math.ceil((expDate - today) / (1000 * 60 * 60 * 24));
            let color = '#666';
            let badge = '';
            if (daysUntilExpiry < 0) {
                color = '#dc3545';
                badge = ' 🔴 EXPIRED';
            } else if (daysUntilExpiry <= 7) {
                color = '#dc3545';
                badge = ` ⚠️ ${daysUntilExpiry}d left`;
            } else if (daysUntilExpiry <= 30) {
                color = '#ffc107';
                badge = ` ⚡ ${daysUntilExpiry}d left`;
            }
            return `<span style="color:${color};font-size:12px;font-weight:600;">${expdate}${badge}</span>`;
        }

        function filterAndSortCustomers(searchTerm = '') {
            let filtered = customers.filter(c => 
                c.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                c.contact.toLowerCase().includes(searchTerm.toLowerCase())
            );
            
            if (currentQuickFilter !== 'all') {
                const today = new Date();
                const thirtyDaysLater = new Date(today.getTime() + (30 * 24 * 60 * 60 * 1000));
                
                filtered = filtered.filter(c => {
                    switch(currentQuickFilter) {
                        case 'voucher-ready': return c.points >= 50;
                        case 'high-points': return c.points >= 150;
                        case 'expiring':
                            if (!c.expdate || c.expdate === '0000-00-00') return false;
                            const expDate = new Date(c.expdate);
                            return expDate >= today && expDate <= thirtyDaysLater && c.points > 0;
                        default: return true;
                    }
                });
            }
            
            if (currentPointsFilter !== 'all') {
                filtered = filtered.filter(c => {
                    switch(currentPointsFilter) {
                        case 'voucher-ready': return c.points >= 50;
                        case 'high': return c.points >= 150;
                        case 'medium': return c.points >= 50 && c.points < 150;
                        case 'low': return c.points > 0 && c.points < 50;
                        case 'zero': return c.points === 0;
                        default: return true;
                    }
                });
            }
            
            filtered.sort((a, b) => {
                switch(currentSort) {
                    case 'points-desc': return b.points - a.points;
                    case 'points-asc': return a.points - b.points;
                    case 'name-asc': return a.name.localeCompare(b.name);
                    case 'expdate-asc': 
                        if (!a.expdate) return 1;
                        if (!b.expdate) return -1;
                        return new Date(a.expdate) - new Date(b.expdate);
                    case 'timestamp-desc': return new Date(b.timestamp) - new Date(a.timestamp);
                    default: return 0;
                }
            });
            
            return filtered;
        }

        function renderCustomerTable(searchTerm = '') {
            const tbody = document.getElementById('customerTableBody');
            const filteredCustomers = filterAndSortCustomers(searchTerm);
            
            if (filteredCustomers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">No customers found</td></tr>';
                return;
            }
            
            const html = filteredCustomers.map(c => `
                <tr>
                    <td><strong>${c.name}</strong></td>
                    <td>${c.contact}</td>
                    <td><span class="points-badge">${c.points} pts</span></td>
                    <td>${getVoucherStatus(c.points)}</td>
                    <td>${formatExpiryDate(c.expdate)}</td>
                    <td style="font-size:12px; color:#666;">${c.timestamp}</td>
                    <td>
                        ${c.points >= 50 ? `
                            <button class="action-btn" style="background:#28a745;margin-bottom:5px;" 
                                    onclick="redeemVoucher(${c.id}, '${c.points >= 150 ? '20%' : '10%'}')">
                                Redeem ${c.points >= 150 ? '20%' : '10%'}
                            </button>
                        ` : ''}
                        <button class="action-btn delete" 
                                onclick="deleteCustomer(${c.id}, '${c.name.replace(/'/g, "\\'")}')">Delete</button>
                    </td>
                </tr>
            `).join('');
            
            tbody.innerHTML = html;
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            setTimeout(() => { alertContainer.innerHTML = ''; }, 5000);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showVoucherNotification(message, voucher) {
            const modal = document.getElementById('voucherModal');
            document.getElementById('voucherModal