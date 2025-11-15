<?php
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Cache control headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'panyeros');

$positive_feedback = [];
$negative_feedback = [];
$stats = ['total' => 0, 'positive' => 0, 'negative' => 0, 'avg_rating' => 0];
$error = '';

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch positive feedback (3-5 stars)
    $stmt_pos = $conn->query("SELECT ID, comment, rating, created_at FROM feedback WHERE rating >= 3 AND comment != '' ORDER BY ID DESC");
    $positive_feedback = $stmt_pos->fetchAll(PDO::FETCH_ASSOC);

    // Fetch negative feedback (1-2 stars)
    $stmt_neg = $conn->query("SELECT ID, comment, rating, created_at FROM feedback WHERE rating <= 2 AND comment != '' ORDER BY ID DESC");
    $negative_feedback = $stmt_neg->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $stmt_stats = $conn->query("SELECT COUNT(*) as total, AVG(rating) as avg_rating FROM feedback WHERE comment != ''");
    $stats_data = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    $stats['total'] = $stats_data['total'];
    $stats['positive'] = count($positive_feedback);
    $stats['negative'] = count($negative_feedback);
    $stats['avg_rating'] = round($stats_data['avg_rating'], 1);

} catch(PDOException $e) {
    $error = 'Could not fetch feedback: ' . $e->getMessage();
}

// Add created_at column if it doesn't exist
try {
    $conn->exec("ALTER TABLE feedback ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
} catch(PDOException $e) {
    // Column already exists, ignore error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback - Panyeros sa Kusina</title>
    <link rel="stylesheet" href="feed.css">
    <style>
        .stats-bar {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            flex: 1;
            min-width: 200px;
            text-align: center;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #D4874B;
        }
        
        .stat-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .card {
            flex: 1;
            min-width: 300px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: #D4874B;
            color: #fff;
            padding: 15px 25px;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header.negative {
            background: #dc3545;
        }
        
        .card-header.positive {
            background: #28a745;
        }
        
        .feedback-count {
            background: rgba(255,255,255,0.3);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .card-body {
            padding: 20px;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .feedback-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        
        .feedback-item:hover {
            background: #f8f9fa;
        }
        
        .feedback-item:last-child {
            border-bottom: none;
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .customer-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            background: #f0f0f0;
            padding: 4px 10px;
            border-radius: 12px;
        }
        
        .feedback-date {
            font-size: 11px;
            color: #999;
        }
        
        .feedback-text {
            color: #666;
            line-height: 1.6;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .feedback-rating {
            color: #D4874B;
            font-size: 16px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            font-style: italic;
        }
        
        .print-btn {
            padding: 8px 16px;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .print-btn:hover {
            background: #0056b3;
        }
        
        @media print {
            .sidebar, .top-bar, .print-btn, .stats-bar {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .card-body {
                max-height: none;
            }
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
                💬 Customer Feedback Management
            </h1>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </div>
        
        <div class="content-area">
            <?php if ($error): ?>
                <div class="card" style="border-color: #f5c6cb; background: #f8d7da; color: #721c24; padding: 20px; width: 100%; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Bar -->
            <div class="stats-bar">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Feedbacks</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" style="color: #28a745;"><?php echo $stats['positive']; ?></div>
                    <div class="stat-label">Positive (3-5 ⭐)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" style="color: #dc3545;"><?php echo $stats['negative']; ?></div>
                    <div class="stat-label">Negative (1-2 ⭐)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $stats['avg_rating']; ?> ⭐</div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>

            <div class="container">
                <!-- Negative Feedback -->
                <div class="card">
                    <div class="card-header negative">
                        <span>⚠️ Negative Feedback (1-2 Stars)</span>
                        <span class="feedback-count"><?php echo count($negative_feedback); ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($negative_feedback)): ?>
                            <div class="empty-state">
                                <p>🎉 No negative feedback! Keep up the great work!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($negative_feedback as $feedback): ?>
                                <div class="feedback-item">
                                    <div class="feedback-header">
                                        <span class="customer-name">Anonymous Customer</span>
                                        <span class="feedback-date">
                                            <?php 
                                            $date = isset($feedback['created_at']) ? $feedback['created_at'] : 'Recently';
                                            echo htmlspecialchars($date); 
                                            ?>
                                        </span>
                                    </div>
                                    <div class="feedback-text"><?php echo htmlspecialchars($feedback['comment']); ?></div>
                                    <div class="feedback-rating"><?php echo str_repeat('⭐', (int)$feedback['rating']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Positive Feedback -->
                <div class="card">
                    <div class="card-header positive">
                        <span>✅ Positive Feedback (3-5 Stars)</span>
                        <span class="feedback-count"><?php echo count($positive_feedback); ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($positive_feedback)): ?>
                            <div class="empty-state">
                                <p>No positive feedback submitted yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($positive_feedback as $feedback): ?>
                                <div class="feedback-item">
                                    <div class="feedback-header">
                                        <span class="customer-name">Anonymous Customer</span>
                                        <span class="feedback-date">
                                            <?php 
                                            $date = isset($feedback['created_at']) ? $feedback['created_at'] : 'Recently';
                                            echo htmlspecialchars($date); 
                                            ?>
                                        </span>
                                    </div>
                                    <div class="feedback-text"><?php echo htmlspecialchars($feedback['comment']); ?></div>
                                    <div class="feedback-rating"><?php echo str_repeat('⭐', (int)$feedback['rating']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</body>
</html>
<?php ob_end_flush(); ?>