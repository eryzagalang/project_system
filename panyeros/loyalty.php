<?php
include 'db/db_connect.php';

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
            $sql = "SELECT * FROM loyalty ORDER BY points DESC";
            $result = $conn->query($sql);
            
            if (!$result) {
                jsonResponse(['success' => false, 'message' => $conn->error]);
            }
            
            $customers = [];
            while ($row = $result->fetch_assoc()) {
                $customers[] = [
                    'id' => (int)$row['ID'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'points' => (int)$row['points'],
                    'note' => $row['note'],
                    'timestamp' => $row['timestamp']
                ];
            }
            
            jsonResponse(['success' => true, 'customers' => $customers]);
            break;

        case 'addTransaction':
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $purchaseAmount = (float)($_POST['purchase_amount'] ?? 0);
            $note = trim($_POST['note'] ?? '');

            if (!$name || !$email || $purchaseAmount <= 0) {
                jsonResponse(['success' => false, 'message' => 'Please fill all required fields with valid purchase amount']);
            }

            // Calculate points: ₱50 = 1 point
            $earnedPoints = floor($purchaseAmount / 50);

            if ($earnedPoints < 1) {
                jsonResponse(['success' => false, 'message' => 'Minimum purchase of ₱50 required to earn points']);
            }

            // Check if customer exists
            $stmt = $conn->prepare("SELECT ID, points FROM loyalty WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $timestamp = date('Y-m-d H:i:s');
            $transactionNote = $note ? $note : "Purchase: ₱{$purchaseAmount} = {$earnedPoints} pts";
            
            if ($row = $result->fetch_assoc()) {
                // Update existing customer
                $newPoints = (int)$row['points'] + $earnedPoints;
                $updateStmt = $conn->prepare("UPDATE loyalty SET name=?, points=?, note=?, timestamp=? WHERE ID=?");
                $updateStmt->bind_param('sissi', $name, $newPoints, $transactionNote, $timestamp, $row['ID']);
                
                if ($updateStmt->execute()) {
                    // Check for voucher eligibility
                    $voucher = null;
                    if ($newPoints >= 150) {
                        $voucher = '20% discount voucher available!';
                    } elseif ($newPoints >= 50) {
                        $voucher = '10% discount voucher available!';
                    }
                    
                    jsonResponse([
                        'success' => true, 
                        'message' => "Purchase of ₱{$purchaseAmount} earned {$earnedPoints} points!",
                        'earned_points' => $earnedPoints,
                        'total_points' => $newPoints,
                        'voucher' => $voucher
                    ]);
                }
            } else {
                // Create new customer
                $insertStmt = $conn->prepare("INSERT INTO loyalty (name, email, points, note, timestamp) VALUES (?, ?, ?, ?, ?)");
                $insertStmt->bind_param('ssiss', $name, $email, $earnedPoints, $transactionNote, $timestamp);
                
                if ($insertStmt->execute()) {
                    // Check for voucher eligibility
                    $voucher = null;
                    if ($earnedPoints >= 150) {
                        $voucher = '20% discount voucher available!';
                    } elseif ($earnedPoints >= 50) {
                        $voucher = '10% discount voucher available!';
                    }
                    
                    jsonResponse([
                        'success' => true, 
                        'message' => "New customer! Purchase of ₱{$purchaseAmount} earned {$earnedPoints} points!",
                        'earned_points' => $earnedPoints,
                        'total_points' => $earnedPoints,
                        'voucher' => $voucher
                    ]);
                }
            }
            
            jsonResponse(['success' => false, 'message' => 'Failed to process transaction']);
            break;

        case 'redeemVoucher':
            $id = (int)($_POST['id'] ?? 0);
            $voucherType = $_POST['voucher_type'] ?? '';
            
            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'Invalid customer ID']);
            }

            // Get customer current points
            $stmt = $conn->prepare("SELECT name, points FROM loyalty WHERE ID=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $currentPoints = (int)$row['points'];
                $customerName = $row['name'];
                
                // Check voucher eligibility
                if ($voucherType === '10%' && $currentPoints >= 50) {
                    // Reset points to 0 after redemption
                    $updateStmt = $conn->prepare("UPDATE loyalty SET points=0, note='Redeemed 10% voucher', timestamp=NOW() WHERE ID=?");
                    $updateStmt->bind_param('i', $id);
                    
                    if ($updateStmt->execute()) {
                        jsonResponse([
                            'success' => true,
                            'message' => "🎉 {$customerName} redeemed 10% discount voucher! Points reset to 0.",
                            'voucher' => '10% discount'
                        ]);
                    }
                } elseif ($voucherType === '20%' && $currentPoints >= 150) {
                    // Reset points to 0 after redemption
                    $updateStmt = $conn->prepare("UPDATE loyalty SET points=0, note='Redeemed 20% voucher', timestamp=NOW() WHERE ID=?");
                    $updateStmt->bind_param('i', $id);
                    
                    if ($updateStmt->execute()) {
                        jsonResponse([
                            'success' => true,
                            'message' => "🎉 {$customerName} redeemed 20% discount voucher! Points reset to 0.",
                            'voucher' => '20% discount'
                        ]);
                    }
                } else {
                    jsonResponse(['success' => false, 'message' => 'Insufficient points for this voucher']);
                }
            }
            
            jsonResponse(['success' => false, 'message' => 'Customer not found']);
            break;

        case 'deleteCustomer':
            $id = (int)($_POST['id'] ?? 0);
            
            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'Invalid customer ID']);
            }

            $stmt = $conn->prepare("DELETE FROM loyalty WHERE ID=?");
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                jsonResponse(['success' => true, 'message' => 'Customer deleted successfully']);
            }
            
            jsonResponse(['success' => false, 'message' => 'Failed to delete customer']);
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
</head>
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
            <li class="nav-item">
                <a href="inventory.php">
                    <span class="nav-icon">📦</span>
                    <span>Inventory</span>
                </a>
            </li>
            <li class="nav-item active">
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
                Loyalty Program
                <span class="star-icon">⭐</span>
            </h1>
            <button class="logout-btn" onclick="alert('Logout functionality')">Logout</button>
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
            </div>
            
            <div class="container">
                <div>
                    <div class="card">
                        <div class="card-header">
                            ➕ Add Customer Purchase
                        </div>
                        <div class="card-body">
                            <div id="alertContainer"></div>
                            
                            <div style="background: rgba(255,193,7,0.2); padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                                <strong>💰 Points System:</strong><br>
                                • Every ₱50 purchase = 1 point<br>
                                • 50 points = 10% discount voucher<br>
                                • 150 points = 20% discount voucher<br>
                                • Points reset to 0 after redemption
                            </div>
                            
                            <form id="loyaltyForm">
                                <div class="form-group">
                                    <label class="form-label">Customer Name</label>
                                    <input type="text" id="customerName" class="form-control" 
                                           placeholder="Enter customer name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email / Phone</label>
                                    <input type="text" id="contact" class="form-control" 
                                           placeholder="email@example.com or 09171234567" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Purchase Amount (₱)</label>
                                    <input type="number" id="purchaseAmount" class="form-control" 
                                           placeholder="e.g., 250" min="50" step="0.01" required>
                                    <div class="help-text">💡 Minimum ₱50 to earn points</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Calculated Points</label>
                                    <input type="text" id="calculatedPoints" class="form-control" 
                                           readonly style="background: rgba(0,0,0,0.05); font-weight: bold; color: #d4874b;">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Transaction Note (Optional)</label>
                                    <textarea id="note" class="form-control" 
                                              placeholder="Add a note about this transaction..."></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Purchase Timestamp</label>
                                    <input type="text" id="timestamp" class="form-control" readonly 
                                           style="background: rgba(0,0,0,0.05); cursor: not-allowed;">
                                    <div class="help-text">🕒 Auto-generated timestamp for this transaction</div>
                                </div>
                                
                                <button type="submit" class="submit-btn">Submit Purchase</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="top-customers">
                        <div class="top-customers-header">
                            🏆 Top Loyalty Customers
                        </div>
                        <div id="topCustomersList"></div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        👥 Customer List
                    </div>
                    <div class="card-body">
                        <div class="search-box">
                            <input type="text" id="searchInput" class="search-input" 
                                   placeholder="🔍 Search customers...">
                        </div>
                        <div class="table-container">
                            <table class="customer-table">
                                <thead>
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Contact</th>
                                        <th>Points</th>
                                        <th>Voucher Status</th>
                                        <th>Last Purchase</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="customerTableBody">
                                    <tr>
                                        <td colspan="6" style="text-align:center;padding:30px;color:#999;">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Voucher Notification Modal -->
    <div id="voucherModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="modal-close" onclick="closeVoucherModal()">&times;</span>
            <div class="modal-header" id="voucherModalHeader"></div>
            <div id="voucherModalBody" style="padding: 20px; text-align: center;"></div>
        </div>
    </div>

    <script>
        let customers = [];

        // Calculate points in real-time
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
                    renderTopCustomers();
                    renderCustomerTable();
                } else {
                    showAlert('❌ Error loading customers: ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error connecting to database: ' + error.message, 'error');
            }
        }

        function updateStats() {
            const customerCount = customers.length;
            const totalPoints = customers.reduce((sum, c) => sum + c.points, 0);
            const avgPoints = customerCount > 0 ? (totalPoints / customerCount).toFixed(1) : 0;
            
            document.getElementById('totalCustomers').textContent = customerCount;
            document.getElementById('totalPoints').textContent = totalPoints.toLocaleString();
            document.getElementById('avgPoints').textContent = avgPoints;
        }

        function renderTopCustomers() {
            const topCustomers = [...customers]
                .sort((a, b) => b.points - a.points)
                .slice(0, 5);
            
            const html = topCustomers.map((customer, index) => `
                <div class="top-customer-item">
                    <span class="customer-name">${index + 1}. ${customer.name}</span>
                    <span class="customer-points">${customer.points} pts</span>
                </div>
            `).join('');
            
            document.getElementById('topCustomersList').innerHTML = html || '<p style="text-align:center;color:#999;">No customers yet</p>';
        }

        function getVoucherStatus(points) {
            if (points >= 150) {
                return '<span style="background:#28a745;color:#fff;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">🎉 20% Voucher Ready!</span>';
            } else if (points >= 50) {
                return '<span style="background:#ffc107;color:#000;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">🎁 10% Voucher Ready!</span>';
            } else {
                const needed = 50 - points;
                return `<span style="color:#666;font-size:12px;">${needed} pts to voucher</span>`;
            }
        }

        function renderCustomerTable(filter = '') {
            const tbody = document.getElementById('customerTableBody');
            const filteredCustomers = customers.filter(c => 
                c.name.toLowerCase().includes(filter.toLowerCase()) ||
                c.email.toLowerCase().includes(filter.toLowerCase())
            );
            
            if (filteredCustomers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#999;">No customers found</td></tr>';
                return;
            }
            
            const html = filteredCustomers.map(customer => `
                <tr>
                    <td><strong>${customer.name}</strong></td>
                    <td>${customer.email}</td>
                    <td><span class="points-badge">${customer.points} pts</span></td>
                    <td>${getVoucherStatus(customer.points)}</td>
                    <td style="font-size:12px; color:#666;">${customer.timestamp}</td>
                    <td>
                        ${customer.points >= 50 ? `
                            <button class="history-btn" style="background:#28a745;margin-bottom:5px;" 
                                    onclick="redeemVoucher(${customer.id}, '${customer.points >= 150 ? '20%' : '10%'}', ${customer.points})">
                                Redeem ${customer.points >= 150 ? '20%' : '10%'}
                            </button>
                        ` : ''}
                        <button class="history-btn" style="background:#dc3545;" 
                                onclick="deleteCustomer(${customer.id}, '${customer.name}')">
                            Delete
                        </button>
                    </td>
                </tr>
            `).join('');
            
            tbody.innerHTML = html;
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = `
                <div class="alert alert-${type}">
                    ${message}
                </div>
            `;
            
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showVoucherNotification(message, voucher) {
            const modal = document.getElementById('voucherModal');
            document.getElementById('voucherModalHeader').innerHTML = '🎉 Voucher Available!';
            document.getElementById('voucherModalBody').innerHTML = `
                <div style="font-size: 24px; margin-bottom: 15px;">🎊</div>
                <div style="font-size: 18px; font-weight: bold; color: #28a745; margin-bottom: 10px;">
                    ${voucher}
                </div>
                <div style="color: #666;">
                    ${message}
                </div>
            `;
            modal.style.display = 'flex';
        }

        function closeVoucherModal() {
            document.getElementById('voucherModal').style.display = 'none';
        }

        async function redeemVoucher(id, voucherType, currentPoints) {
            const confirmMsg = `Redeem ${voucherType} discount voucher? This will reset points to 0.`;
            if (!confirm(confirmMsg)) return;

            const formData = new FormData();
            formData.append('action', 'redeemVoucher');
            formData.append('id', id);
            formData.append('voucher_type', voucherType);

            try {
                const response = await fetch('loyalty.php', {
                    method: 'POST',
                    body: formData
                });
                
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
                const response = await fetch('loyalty.php', {
                    method: 'POST',
                    body: formData
                });
                
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
        
        function updateTimestamp() {
            const now = new Date();
            const formatted = now.toISOString().slice(0, 19).replace('T', ' ');
            document.getElementById('timestamp').value = formatted;
        }
        
        setInterval(updateTimestamp, 1000);
        updateTimestamp();

        document.getElementById('loyaltyForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const name = document.getElementById('customerName').value.trim();
            const email = document.getElementById('contact').value.trim();
            const purchaseAmount = parseFloat(document.getElementById('purchaseAmount').value);
            const note = document.getElementById('note').value.trim();
            
            const formData = new FormData();
            formData.append('action', 'addTransaction');
            formData.append('name', name);
            formData.append('email', email);
            formData.append('purchase_amount', purchaseAmount);
            formData.append('note', note);

            try {
                const response = await fetch('loyalty.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('✅ ' + data.message, 'success');
                    
                    if (data.voucher) {
                        setTimeout(() => {
                            showVoucherNotification(data.message, data.voucher);
                        }, 1000);
                    }
                    
                    this.reset();
                    updateTimestamp();
                    document.getElementById('calculatedPoints').value = '';
                    loadCustomers();
                } else {
                    showAlert('❌ ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error: ' + error.message, 'error');
            }
        });

        document.getElementById('searchInput').addEventListener('input', function(e) {
            renderCustomerTable(e.target.value);
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('voucherModal');
            if (e.target === modal) {
                closeVoucherModal();
            }
        });

        // Initialize
        loadCustomers();
    </script>
</body>
</html>