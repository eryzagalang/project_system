<?php
ob_start();
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'panyeros');

// Handle AJAX requests
if (isset($_GET['action']) || isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $action = $_GET['action'] ?? $_POST['action'];
        
        switch($action) {
            case 'getFeedback':
                $month = $_GET['month'] ?? date('Y-m');
                $rating_filter = $_GET['rating'] ?? 'all';
                
                $sql = "SELECT ID, name, comment, rating, created_at 
                        FROM feedback 
                        WHERE archived = 0 
                        AND DATE_FORMAT(created_at, '%Y-%m') = :month";
                
                if ($rating_filter === 'positive') {
                    $sql .= " AND rating >= 3";
                } elseif ($rating_filter === 'negative') {
                    $sql .= " AND rating <= 2";
                }
                
                $sql .= " ORDER BY created_at DESC";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute(['month' => $month]);
                $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'feedback' => $feedback]);
                break;
                
            case 'getHistory':
                $limit = $_GET['limit'] ?? 100;
                
                $sql = "SELECT * FROM feedback_history 
                        ORDER BY action_date DESC 
                        LIMIT :limit";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
                $stmt->execute();
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'history' => $history]);
                break;
                
            case 'getStats':
                $month = $_GET['month'] ?? date('Y-m');
                
                // Total feedback for month
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM feedback 
                                       WHERE archived = 0 
                                       AND DATE_FORMAT(created_at, '%Y-%m') = :month");
                $stmt->execute(['month' => $month]);
                $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // Positive feedback (3-5 stars)
                $stmt = $conn->prepare("SELECT COUNT(*) as positive FROM feedback 
                                       WHERE archived = 0 
                                       AND rating >= 3 
                                       AND DATE_FORMAT(created_at, '%Y-%m') = :month");
                $stmt->execute(['month' => $month]);
                $positive = $stmt->fetch(PDO::FETCH_ASSOC)['positive'];
                
                // Negative feedback (1-2 stars)
                $stmt = $conn->prepare("SELECT COUNT(*) as negative FROM feedback 
                                       WHERE archived = 0 
                                       AND rating <= 2 
                                       AND DATE_FORMAT(created_at, '%Y-%m') = :month");
                $stmt->execute(['month' => $month]);
                $negative = $stmt->fetch(PDO::FETCH_ASSOC)['negative'];
                
                // Average rating
                $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM feedback 
                                       WHERE archived = 0 
                                       AND DATE_FORMAT(created_at, '%Y-%m') = :month");
                $stmt->execute(['month' => $month]);
                $avg = $stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'];
                
                echo json_encode([
                    'success' => true,
                    'total' => $total,
                    'positive' => $positive,
                    'negative' => $negative,
                    'average' => round($avg, 2)
                ]);
                break;
                
            case 'deleteFeedback':
                $id = $_POST['id'] ?? 0;
                
                // Get feedback details before deletion
                $stmt = $conn->prepare("SELECT * FROM feedback WHERE ID = :id");
                $stmt->execute(['id' => $id]);
                $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($feedback) {
                    // Log to history
                    $stmt = $conn->prepare("INSERT INTO feedback_history 
                                          (feedback_id, name, comment, rating, original_date, action_type, deleted_by, notes) 
                                          VALUES (:fid, :name, :comment, :rating, :orig_date, 'deleted', :user, :notes)");
                    $stmt->execute([
                        'fid' => $id,
                        'name' => $feedback['name'],
                        'comment' => $feedback['comment'],
                        'rating' => $feedback['rating'],
                        'orig_date' => $feedback['created_at'],
                        'user' => $_SESSION['name'] ?? 'Admin',
                        'notes' => 'Individual feedback deleted'
                    ]);
                    
                    // Delete feedback
                    $stmt = $conn->prepare("DELETE FROM feedback WHERE ID = :id");
                    $stmt->execute(['id' => $id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Feedback deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Feedback not found']);
                }
                break;
                
            case 'deleteMonthFeedback':
                $month = $_POST['month'] ?? '';
                
                if (empty($month)) {
                    echo json_encode(['success' => false, 'message' => 'Month is required']);
                    break;
                }
                
                // Get all feedback for the month
                $stmt = $conn->prepare("SELECT * FROM feedback 
                                       WHERE archived = 0 
                                       AND DATE_FORMAT(created_at, '%Y-%m') = :month");
                $stmt->execute(['month' => $month]);
                $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $deleted_count = 0;
                foreach ($feedbacks as $feedback) {
                    // Log to history
                    $stmt = $conn->prepare("INSERT INTO feedback_history 
                                          (feedback_id, name, comment, rating, original_date, action_type, deleted_by, notes) 
                                          VALUES (:fid, :name, :comment, :rating, :orig_date, 'deleted', :user, :notes)");
                    $stmt->execute([
                        'fid' => $feedback['ID'],
                        'name' => $feedback['name'],
                        'comment' => $feedback['comment'],
                        'rating' => $feedback['rating'],
                        'orig_date' => $feedback['created_at'],
                        'user' => $_SESSION['name'] ?? 'Admin',
                        'notes' => "Bulk delete for month: $month"
                    ]);
                    
                    $deleted_count++;
                }
                
                // Delete all feedback for the month
                $stmt = $conn->prepare("DELETE FROM feedback 
                                       WHERE archived = 0 
                                       AND DATE_FORMAT(created_at, '%Y-%m') = :month");
                $stmt->execute(['month' => $month]);
                
                echo json_encode([
                    'success' => true, 
                    'message' => "$deleted_count feedback(s) deleted for $month"
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback - Panyeros Kusina</title>
    <link rel="stylesheet" href="invent.css">
    
    <style>
        .filter-bar {
            background: rgba(255,255,255,0.3);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            font-weight: 600;
            font-size: 13px;
            color: #2d2d2d;
        }
        
        .filter-select, .filter-input {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            width: 100%;
        }
        
        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: #638ECB;
            background: #fff;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-print {
            background: #28a745;
            color: white;
        }
        
        .btn-print:hover {
            background: #218838;
        }
        
        .btn-history {
            background: #638ECB;
            color: white;
        }
        
        .btn-history:hover {
            background: #4A6FA5;
        }
        
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #638ECB;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .card {
            flex: 1;
            min-width: 300px;
        }
        
        .feedback-item {
            position: relative;
            padding-right: 50px;
        }
        
        .feedback-actions {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        .btn-delete-single {
            padding: 5px 10px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-delete-single:hover {
            background: #c82333;
        }
        
        .tab-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: #666;
            transition: all 0.3s;
        }
        
        .tab-btn:hover {
            color: #638ECB;
        }
        
        .tab-btn.active {
            color: #638ECB;
            border-bottom-color: #638ECB;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .history-table th {
            background: #2d2d2d;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 13px;
        }
        
        .history-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }
        
        .history-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .action-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .action-deleted {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-archived {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Print Styles */
        @media print {
            .sidebar,
            .top-bar,
            .filter-bar,
            .action-buttons,
            .feedback-actions,
            .btn,
            .tab-navigation {
                display: none !important;
            }
            
            body {
                background: white;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .card {
                page-break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .print-header {
                display: block !important;
            }
            
            .stats-bar {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            @page {
                margin: 1cm;
            }
        }
        
        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
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
    </style>
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
            <li class="nav-item">
                <a href="loyalty.php">
                    <span class="nav-icon">💳</span>
                    <span>Loyalty Cards</span>
                </a>
            </li>
            <li class="nav-item active">
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
                💬 Customer Feedback
            </h1>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </div>
        
        <div class="content-area">
            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-btn active" onclick="switchTab('feedback')">
                    💬 Feedback
                </button>
                <button class="tab-btn" onclick="switchTab('history')">
                    📜 History
                </button>
            </div>
            
            <!-- Feedback Tab -->
            <div id="feedbackTab" class="tab-content active">
                <!-- Filter Bar -->
                <div class="filter-bar">
                    <div class="filter-group">
                        <label class="filter-label">Select Month</label>
                        <input type="month" id="monthFilter" class="filter-input" onchange="loadFeedback()">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Rating Filter</label>
                        <select id="ratingFilter" class="filter-select" onchange="loadFeedback()">
                            <option value="all">All Ratings</option>
                            <option value="positive">Positive (3-5 ⭐)</option>
                            <option value="negative">Negative (1-2 ⭐)</option>
                        </select>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn btn-print" onclick="printFeedback()">
                            🖨️ Print Report
                        </button>
                        <button class="btn btn-danger" onclick="deleteMonthFeedback()">
                            🗑️ Delete Month
                        </button>
                    </div>
                </div>
                
                <!-- Stats Bar -->
                <div class="stats-bar">
                    <div class="stat-card">
                        <div class="stat-value" id="totalFeedback">0</div>
                        <div class="stat-label">Total Feedback</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="positiveFeedback">0</div>
                        <div class="stat-label">Positive (3-5 ⭐)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="negativeFeedback">0</div>
                        <div class="stat-label">Negative (1-2 ⭐)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="avgRating">0</div>
                        <div class="stat-label">Average Rating</div>
                    </div>
                </div>
                
                <!-- Print Header -->
                <div class="print-header">
                    <h1>💬 Panyeros Kusina - Feedback Report</h1>
                    <p id="printDate"></p>
                    <p id="printPeriod"></p>
                </div>
                
                <!-- Feedback Cards -->
                <div class="container" id="feedbackContainer">
                    <div class="card">
                        <div class="card-header" style="color: #721c24;">Negative Feedback (1-2 Stars)</div>
                        <div class="card-body">
                            <div id="negativeFeedbackList" class="feedback-list">
                                <p style="text-align: center; color: #999; padding: 20px;">Loading...</p>
                            </div>
                        </div>
                    </div>

                    <div class="card"> 
                        <div class="card-header" style="color: #155724;">Positive Feedback (3-5 Stars)</div>
                        <div class="card-body">
                            <div id="positiveFeedbackList" class="feedback-list">
                                <p style="text-align: center; color: #999; padding: 20px;">Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- History Tab -->
            <div id="historyTab" class="tab-content">
                <div class="filter-bar">
                    <div class="filter-group">
                        <label class="filter-label">Show Records</label>
                        <select id="historyLimit" class="filter-select" onchange="loadHistory()">
                            <option value="50">Last 50</option>
                            <option value="100" selected>Last 100</option>
                            <option value="200">Last 200</option>
                            <option value="500">All Records</option>
                        </select>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn btn-print" onclick="printHistory()">
                            🖨️ Print History
                        </button>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">📜 Feedback History</div>
                    <div class="card-body">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Action</th>
                                    <th>Customer Name</th>
                                    <th>Comment</th>
                                    <th>Rating</th>
                                    <th>Original Date</th>
                                    <th>Deleted By</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                                        Loading history...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let currentMonth = new Date().toISOString().slice(0, 7);
        let feedbackData = [];
        
        // Initialize
        document.getElementById('monthFilter').value = currentMonth;
        loadFeedback();
        loadStats();
        
        // Tab Switching
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            if (tab === 'feedback') {
                document.querySelector('.tab-btn:first-child').classList.add('active');
                document.getElementById('feedbackTab').classList.add('active');
            } else {
                document.querySelector('.tab-btn:last-child').classList.add('active');
                document.getElementById('historyTab').classList.add('active');
                loadHistory();
            }
        }
        
        async function loadFeedback() {
            const month = document.getElementById('monthFilter').value;
            const rating = document.getElementById('ratingFilter').value;
            currentMonth = month;
            
            try {
                const response = await fetch(`feedback.php?action=getFeedback&month=${month}&rating=${rating}`);
                const data = await response.json();
                
                if (data.success) {
                    feedbackData = data.feedback;
                    renderFeedback();
                    loadStats();
                } else {
                    alert('Error loading feedback: ' + data.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function loadStats() {
            const month = document.getElementById('monthFilter').value;
            
            try {
                const response = await fetch(`feedback.php?action=getStats&month=${month}`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalFeedback').textContent = data.total;
                    document.getElementById('positiveFeedback').textContent = data.positive;
                    document.getElementById('negativeFeedback').textContent = data.negative;
                    document.getElementById('avgRating').textContent = data.average || '0';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
        
        function renderFeedback() {
            const positive = feedbackData.filter(f => parseInt(f.rating) >= 3);
            const negative = feedbackData.filter(f => parseInt(f.rating) <= 2);
            
            const negativeList = document.getElementById('negativeFeedbackList');
            const positiveList = document.getElementById('positiveFeedbackList');
            
            if (negative.length === 0) {
                negativeList.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">No negative feedback for this period.</p>';
            } else {
                negativeList.innerHTML = negative.map(f => `
                    <div class="feedback-item">
                        <div class="feedback-actions">
                            <button class="btn-delete-single" onclick="deleteSingleFeedback(${f.ID}, '${f.name.replace(/'/g, "\\'")}')">
                                🗑️ Delete
                            </button>
                        </div>
                        <div class="feedback-header">
                            <span class="customer-name">${f.name}</span>
                            <span class="feedback-date" style="font-size: 11px; color: #999; margin-left: 10px;">
                                ${new Date(f.created_at).toLocaleDateString()}
                            </span>
                        </div>
                        <div class="feedback-text">${f.comment}</div>
                        <div class="feedback-rating">${'⭐'.repeat(parseInt(f.rating))}</div>
                    </div>
                `).join('');
            }
            
            if (positive.length === 0) {
                positiveList.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">No positive feedback for this period.</p>';
            } else {
                positiveList.innerHTML = positive.map(f => `
                    <div class="feedback-item">
                        <div class="feedback-actions">
                            <button class="btn-delete-single" onclick="deleteSingleFeedback(${f.ID}, '${f.name.replace(/'/g, "\\'")}')">
                                🗑️ Delete
                            </button>
                        </div>
                        <div class="feedback-header">
                            <span class="customer-name">${f.name}</span>
                            <span class="feedback-date" style="font-size: 11px; color: #999; margin-left: 10px;">
                                ${new Date(f.created_at).toLocaleDateString()}
                            </span>
                        </div>
                        <div class="feedback-text">${f.comment}</div>
                        <div class="feedback-rating">${'⭐'.repeat(parseInt(f.rating))}</div>
                    </div>
                `).join('');
            }
        }
        
        async function deleteSingleFeedback(id, name) {
            if (!confirm(`Are you sure you want to delete feedback from "${name}"?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'deleteFeedback');
            formData.append('id', id);
            
            try {
                const response = await fetch('feedback.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ ' + data.message);
                    loadFeedback();
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (error) {
                alert('❌ Error: ' + error.message);
            }
        }
        
        async function deleteMonthFeedback() {
            const month = document.getElementById('monthFilter').value;
            const monthName = new Date(month + '-01').toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
            
            if (!confirm(`⚠️ WARNING: This will delete ALL feedback from ${monthName}.\n\nThis action cannot be undone. Are you sure?`)) {
                return;
            }
            
            if (!confirm(`🚨 FINAL CONFIRMATION: Delete all feedback from ${monthName}?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'deleteMonthFeedback');
            formData.append('month', month);
            
            try {
                const response = await fetch('feedback.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ ' + data.message);
                    loadFeedback();
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (error) {
                alert('❌ Error: ' + error.message);
            }
        }
        
        async function loadHistory() {
            const limit = document.getElementById('historyLimit').value;
            
            try {
                const response = await fetch(`feedback.php?action=getHistory&limit=${limit}`);
                const data = await response.json();
                
                if (data.success) {
                    renderHistory(data.history);
                } else {