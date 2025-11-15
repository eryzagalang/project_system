<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Database connection - EXACT match to your database
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
            $expdate = date('Y-m-d', strtotime('+3 months')); // Expires in 3 months
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
    <title>Loyalty Program - Panyeros sa Kusina</title>
    <link rel="stylesheet" href="loyal.css">
    <style>
        .filter-bar { margin-bottom: 20px; }
        .filter-row { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .filter-select { flex: 1; min-width: 180px; padding: 10px 15px; border: 2px solid #ddd; border-radius: 5px; font-size: 14px; background: rgba(255,255,255,0.5); cursor: pointer; transition: border-color 0.3s; }
        .filter-select:focus { outline: none; border-color: #D4874B; background: #fff; }
        .filter-btn { flex: 1; min-width: 180px; padding: 10px 20px; border: none; border-radius: 5px; font-size: 14px; color: white; cursor: pointer; transition: opacity 0.3s; font-weight: 600; }
        .filter-btn:hover { opacity: 0.9; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 12px; max-width: 500px; width: 90%; box-shadow: 0 5px 20px rgba(0,0,0,0.3); position: relative; }
        .modal-header { background: #D4874B; color: white; padding: 20px; border-radius: 12px 12px 0 0; font-size: 20px; font-weight: 600; }
        .modal-close { position: absolute; right: 15px; top: 15px; font-size: 28px; font-weight: bold; color: white; cursor: pointer; line-height: 1; }
        .modal-close:hover { color: #f0f0f0; }
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
            <h1 class="page-header-title">Loyalty Program <span class="star-icon">⭐</span></h1>
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
                <div class="stat-box" style="background: #ffc107;">
                    <div class="stat-value" id="expiringCount">0</div>
                    <div class="stat-label">Expiring Soon (30 days)</div>
                </div>
            </div>
            
            <div class="container">
                <div>
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
                                    <input type="text" id="calculatedPoints" class="form-control" readonly style="background: rgba(0,0,0,0.05); font-weight: bold; color: #d4874b;">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Transaction Note (Optional)</label>
                                    <textarea id="note" class="form-control" placeholder="Add a note..."></textarea>
                                </div>
                                <button type="submit" class="submit-btn">Submit Purchase</button>
                            </form>
                        </div>
                    </div>
                    <div class="top-customers">
                        <div class="top-customers-header">🏆 Top Loyalty Customers</div>
                        <div id="topCustomersList"></div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">👥 Customer List</div>
                    <div class="card-body">
                        <div class="filter-bar">
                            <div class="search-box">
                                <input type="text" id="searchInput" class="search-input" placeholder="🔍 Search customers...">
                            </div>
                            <div class="filter-row">
                                <select id="sortFilter" class="filter-select">
                                    <option value="points-desc">Sort: Highest Points</option>
                                    <option value="points-asc">Sort: Lowest Points</option>
                                    <option value="name-asc">Sort: Name (A-Z)</option>
                                    <option value="expdate-asc">Sort: Expiring Soon</option>
                                    <option value="timestamp-desc">Sort: Recent Activity</option>
                                </select>
                                <select id="pointsFilter" class="filter-select">
                                    <option value="all">All Points</option>
                                    <option value="voucher-ready">Voucher Ready (50+)</option>
                                    <option value="high">High Points (150+)</option>
                                    <option value="medium">Medium Points (50-149)</option>
                                    <option value="low">Low Points (1-49)</option>
                                    <option value="zero">Zero Points</option>
                                </select>
                                <select id="expiryFilter" class="filter-select">
                                    <option value="all">All Expiry</option>
                                    <option value="expiring-soon">Expiring Soon (30 days)</option>
                                    <option value="expiring-month">Expiring This Month</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>
                            <div class="filter-row" style="margin-top: 10px;">
                                <button onclick="clearFilters()" class="filter-btn" style="background: #6c757d;">
                                    🔄 Clear Filters
                                </button>
                                <button onclick="printCustomers()" class="filter-btn" style="background: #28a745;">
                                    🖨️ Print Customer List
                                </button>
                            </div>
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
        let currentExpiryFilter = 'all';

        document.getElementById('purchaseAmount').addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            const points = Math.floor(amount / 50);
            document.getElementById('calculatedPoints').value = points > 0 ? `${points} points (₱${amount})` : '0 points';
        });

        async function loadCustomers() {
            try {
                console.log('Loading customers from database...');
                const response = await fetch('loyalty.php?action=getCustomers');
                const data = await response.json();
                console.log('Response:', data);
                
                if (data.success) {
                    customers = data.customers;
                    console.log('Loaded customers:', customers);
                    updateStats();
                    renderTopCustomers();
                    renderCustomerTable();
                } else {
                    showAlert('❌ Error: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Fetch error:', error);
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

        function renderTopCustomers() {
            const topCustomers = [...customers].sort((a, b) => b.points - a.points).slice(0, 5);
            const html = topCustomers.map((c, i) => `
                <div class="top-customer-item">
                    <span class="customer-name">${i + 1}. ${c.name}</span>
                    <span class="customer-points">${c.points} pts</span>
                </div>
            `).join('');
            document.getElementById('topCustomersList').innerHTML = html || '<p style="text-align:center;color:#999;">No customers yet</p>';
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
            if (currentExpiryFilter !== 'all') {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                filtered = filtered.filter(c => {
                    if (!c.expdate || c.expdate === '0000-00-00') return false;
                    const expDate = new Date(c.expdate);
                    expDate.setHours(0, 0, 0, 0);
                    const daysUntilExpiry = Math.ceil((expDate - today) / (1000 * 60 * 60 * 24));
                    switch(currentExpiryFilter) {
                        case 'expiring-soon': return daysUntilExpiry >= 0 && daysUntilExpiry <= 30 && c.points > 0;
                        case 'expiring-month': return daysUntilExpiry >= 0 && daysUntilExpiry <= 31 && c.points > 0;
                        case 'expired': return daysUntilExpiry < 0;
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
                            <button class="history-btn" style="background:#28a745;margin-bottom:5px;" 
                                    onclick="redeemVoucher(${c.id}, '${c.points >= 150 ? '20%' : '10%'}')">
                                Redeem ${c.points >= 150 ? '20%' : '10%'}
                            </button>
                        ` : ''}
                        <button class="history-btn" style="background:#dc3545;" 
                                onclick="deleteCustomer(${c.id}, '${c.name}')">Delete</button>
                    </td>
                </tr>
            `).join('');
            tbody.innerHTML = html;
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => { alertContainer.innerHTML = ''; }, 5000);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showVoucherNotification(message, voucher) {
            const modal = document.getElementById('voucherModal');
            document.getElementById('voucherModalHeader').innerHTML = '🎉 Voucher Available!';
            document.getElementById('voucherModalBody').innerHTML = `
                <div style="font-size: 24px; margin-bottom: 15px;">🎊</div>
                <div style="font-size: 18px; font-weight: bold; color: #28a745; margin-bottom: 10px;">${voucher}</div>
                <div style="color: #666;">${message}</div>
            `;
            modal.style.display = 'flex';
        }

        function closeVoucherModal() {
            document.getElementById('voucherModal').style.display = 'none';
        }

        async function redeemVoucher(id, voucherType) {
            if (!confirm(`Redeem ${voucherType} discount voucher? Points will reset to 0.`)) return;
            const formData = new FormData();
            formData.append('action', 'redeemVoucher');
            formData.append('id', id);
            formData.append('voucher_type', voucherType);
            try {
                const response = await fetch('loyalty.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    showAlert('✅ ' + data.message, 'success');
                    loadCustomers();
                } else {
                    showAlert('❌ ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error: ' + error.message, 'error');
            }
        }

        async function deleteCustomer(id, name) {
            if (!confirm(`Delete customer "${name}"?`)) return;
            const formData = new FormData();
            formData.append('action', 'deleteCustomer');
            formData.append('id', id);
            try {
                const response = await fetch('loyalty.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    showAlert('✅ ' + data.message, 'success');
                    loadCustomers();
                } else {
                    showAlert('❌ ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error: ' + error.message, 'error');
            }
        }

        document.getElementById('loyaltyForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'addTransaction');
            formData.append('name', document.getElementById('customerName').value.trim());
            formData.append('contact', document.getElementById('contact').value.trim());
            formData.append('purchase_amount', parseFloat(document.getElementById('purchaseAmount').value));
            formData.append('note', document.getElementById('note').value.trim());
            try {
                const response = await fetch('loyalty.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    showAlert('✅ ' + data.message, 'success');
                    if (data.voucher) setTimeout(() => showVoucherNotification(data.message, data.voucher), 1000);
                    this.reset();
                    document.getElementById('calculatedPoints').value = '';
                    loadCustomers();
                } else {
                    showAlert('❌ ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error: ' + error.message, 'error');
            }
        });

        document.getElementById('searchInput').addEventListener('input', (e) => renderCustomerTable(e.target.value));
        document.getElementById('sortFilter').addEventListener('change', function() {
            currentSort = this.value;
            renderCustomerTable(document.getElementById('searchInput').value);
        });
        document.getElementById('pointsFilter').addEventListener('change', function() {
            currentPointsFilter = this.value;
            renderCustomerTable(document.getElementById('searchInput').value);
        });
        document.getElementById('expiryFilter').addEventListener('change', function() {
            currentExpiryFilter = this.value;
            renderCustomerTable(document.getElementById('searchInput').value);
        });

        window.addEventListener('click', function(e) {
            const modal = document.getElementById('voucherModal');
            if (e.target === modal) closeVoucherModal();
        });

        function clearFilters() {
            // Reset all filters to default
            currentSort = 'points-desc';
            currentPointsFilter = 'all';
            currentExpiryFilter = 'all';
            
            // Reset dropdowns
            document.getElementById('sortFilter').value = 'points-desc';
            document.getElementById('pointsFilter').value = 'all';
            document.getElementById('expiryFilter').value = 'all';
            document.getElementById('searchInput').value = '';
            
            // Re-render table
            renderCustomerTable();
            showAlert('✅ Filters cleared!', 'success');
        }

        function printCustomers() {
            const printWindow = window.open('', '_blank');
            const filteredCustomers = filterAndSortCustomers(document.getElementById('searchInput').value);
            
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Loyalty Card Customers - Panyeros sa Kusina</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #C17A3F; padding-bottom: 15px; }
                        .header h1 { color: #C17A3F; margin: 0; font-size: 28px; }
                        .header p { color: #666; margin: 5px 0; }
                        .stats { display: flex; justify-content: space-around; margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 8px; }
                        .stat-item { text-align: center; }
                        .stat-value { font-size: 24px; font-weight: bold; color: #C17A3F; }
                        .stat-label { font-size: 12px; color: #666; margin-top: 5px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background: #C17A3F; color: white; padding: 12px; text-align: left; font-size: 14px; }
                        td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 13px; }
                        tr:nth-child(even) { background: #f9f9f9; }
                        .points-badge { background: #D4874B; color: white; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 12px; }
                        .voucher-ready { background: #28a745; color: white; padding: 3px 8px; border-radius: 8px; font-size: 11px; }
                        .expired { color: #dc3545; font-weight: bold; }
                        .expiring { color: #ffc107; font-weight: bold; }
                        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; }
                        @media print {
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>🏆 Loyalty Card Customers</h1>
                        <p>Panyeros sa Kusina</p>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                    </div>
                    
                    <div class="stats">
                        <div class="stat-item">
                            <div class="stat-value">${customers.length}</div>
                            <div class="stat-label">Total Customers</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">${customers.reduce((sum, c) => sum + c.points, 0)}</div>
                            <div class="stat-label">Total Points</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">${filteredCustomers.length}</div>
                            <div class="stat-label">Filtered Results</div>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer Name</th>
                                <th>Contact</th>
                                <th>Points</th>
                                <th>Voucher Status</th>
                                <th>Expiry Date</th>
                                <th>Last Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${filteredCustomers.map((c, index) => {
                                let voucherStatus = '';
                                if (c.points >= 150) voucherStatus = '<span class="voucher-ready">20% Voucher Ready</span>';
                                else if (c.points >= 50) voucherStatus = '<span class="voucher-ready">10% Voucher Ready</span>';
                                else voucherStatus = `${50 - c.points} pts to voucher`;
                                
                                let expiryStatus = '';
                                if (c.expdate && c.expdate !== '0000-00-00') {
                                    const today = new Date();
                                    const expDate = new Date(c.expdate);
                                    const daysLeft = Math.ceil((expDate - today) / (1000 * 60 * 60 * 24));
                                    if (daysLeft < 0) expiryStatus = `<span class="expired">${c.expdate} (EXPIRED)</span>`;
                                    else if (daysLeft <= 30) expiryStatus = `<span class="expiring">${c.expdate} (${daysLeft}d left)</span>`;
                                    else expiryStatus = c.expdate;
                                } else {
                                    expiryStatus = 'N/A';
                                }
                                
                                return `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td><strong>${c.name}</strong></td>
                                        <td>${c.contact}</td>
                                        <td><span class="points-badge">${c.points} pts</span></td>
                                        <td>${voucherStatus}</td>
                                        <td>${expiryStatus}</td>
                                        <td>${c.timestamp}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                    
                    <div class="footer">
                        <p><strong>Panyeros sa Kusina</strong> - Loyalty Program Report</p>
                        <p>📞 Contact: info@panyeroskusina.com | 🌐 www.panyeroskusina.com</p>
                        <button class="no-print" onclick="window.print()" style="margin-top: 15px; padding: 10px 30px; background: #C17A3F; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">🖨️ Print This Page</button>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
        }

        // Load customers when page loads
        loadCustomers();
    </script>
</body>
</html>