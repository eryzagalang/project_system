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
        case 'getInventory':
            $sql = "SELECT * FROM inventory ORDER BY ID DESC";
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
                jsonResponse(['success' => true, 'message' => 'Item added successfully', 'id' => $conn->insert_id]);
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
                jsonResponse(['success' => true, 'message' => 'Item updated successfully']);
            }
            
            jsonResponse(['success' => false, 'message' => $stmt->error]);
            break;

        case 'deleteItem':
            $id = (int)($_POST['id'] ?? 0);
            
            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'Invalid item ID']);
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
            <button class="logout-btn" onclick="alert('Logout functionality')">Logout</button>
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
                        📋 Inventory List
                    </div>
                    <div class="card-body">
                        <div class="search-box">
                            <input type="text" id="searchInput" class="search-input" 
                                   placeholder="🔍 Search items...">
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
                                   
                                </tbody>
                            </table>
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

        // Set today's date as default
        document.getElementById('purchaseDate').value = new Date().toISOString().split('T')[0];

        // Load inventory on page load
        loadInventory();

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
                } else {
                    showAlert('❌ ' + data.message, 'error');
                }
            } catch (error) {
                showAlert('❌ Error: ' + error.message, 'error');
            }
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', (e) => {
            renderTable(e.target.value.toLowerCase());
        });

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

        function updateStats() {
            const totalItems = inventoryData.length;
            let lowStockCount = 0;
            let expiringCount = 0;
            let totalQuantity = 0;

            const sevenDaysFromNow = new Date();
            sevenDaysFromNow.setDate(sevenDaysFromNow.getDate() + 7);

            inventoryData.forEach(item => {
                totalQuantity += item.quantity;
                
                if (item.quantity <= item.stocks) {
                    lowStockCount++;
                }

                const expiryDate = new Date(item.expiration_date);
                if (expiryDate <= sevenDaysFromNow && expiryDate >= new Date()) {
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

        function renderTable(filter = '') {
            const tbody = document.getElementById('inventoryTableBody');
            const filteredItems = inventoryData.filter(item => 
                item.item_name.toLowerCase().includes(filter) ||
                item.category.toLowerCase().includes(filter)
            );
            
            if (filteredItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:30px;color:#999;">No items found</td></tr>';
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