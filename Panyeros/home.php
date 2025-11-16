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
define('LOW_STOCK_THRESHOLD', 10);

// Initialize variables
$welcome_name = htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'User');
$total_items = 0;
$low_stock_count = 0;
$total_points = 0;
$customer_count = 0;
$low_stock_items = [];
$top_customers = [];
$recent_feedback = [];
$monthly_feedback = [];

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch Stats
    $stmt = $conn->query("SELECT SUM(stocks) FROM inventory");
    $total_items = $stmt->fetchColumn() ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM inventory WHERE stocks <= :threshold");
    $stmt->bindValue(':threshold', LOW_STOCK_THRESHOLD);
    $stmt->execute();
    $low_stock_count = $stmt->fetchColumn() ?? 0;

    $stmt = $conn->query("SELECT SUM(points) FROM loyalty");
    $total_points = $stmt->fetchColumn() ?? 0;
    
    $stmt = $conn->query("SELECT COUNT(*) FROM loyalty");
    $customer_count = $stmt->fetchColumn() ?? 0;

    // Fetch Lists
    $stmt = $conn->prepare("SELECT item_name, stocks FROM inventory WHERE stocks <= :threshold ORDER BY stocks ASC LIMIT 5");
    $stmt->bindValue(':threshold', LOW_STOCK_THRESHOLD);
    $stmt->execute();
    $low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->query("SELECT name, points FROM loyalty ORDER BY points DESC LIMIT 5");
    $top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->query("SELECT name, comment, rating FROM feedback WHERE archived = 0 ORDER BY ID DESC LIMIT 3");
    $recent_feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Monthly Feedback Data for Chart (last 12 months)
    $stmt = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            DATE_FORMAT(created_at, '%b %Y') as month_label,
            COUNT(*) as total_count,
            SUM(CASE WHEN rating >= 3 THEN 1 ELSE 0 END) as positive_count,
            SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as negative_count,
            AVG(rating) as avg_rating
        FROM feedback 
        WHERE archived = 0 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
        ORDER BY month ASC
    ");
    $monthly_feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Panyeros Kusina</title>
    <link rel="stylesheet" href="invent.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Additional styles for home page specific elements */
        .stats-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-box {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: 1px solid rgba(99, 142, 203, 0.1);
    position: relative;
    overflow: hidden;
}

.stat-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #638ECB, #82A8D8);
}

.stat-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(99, 142, 203, 0.2);
}

.stat-box.alert::before {
    background: linear-gradient(90deg, #ef4444, #f87171);
}

.stat-box-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    background: linear-gradient(135deg, #638ECB, #82A8D8);
    box-shadow: 0 4px 10px rgba(99, 142, 203, 0.3);
}

.stat-box.alert .stat-icon {
    background: linear-gradient(135deg, #ef4444, #f87171);
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 36px;
    font-weight: 800;
    color: #2d2d2d;
    line-height: 1;
    margin-bottom: 8px;
    background: linear-gradient(135deg, #638ECB, #82A8D8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-box.alert .stat-value {
    background: linear-gradient(135deg, #ef4444, #f87171);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-label {
    font-size: 14px;
    font-weight: 600;
    color: #2d2d2d;
    margin-bottom: 4px;
}

.stat-sublabel {
    font-size: 12px;
    color: #666;
}

/* Enhanced Section Cards */
.section-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border: 1px solid rgba(99, 142, 203, 0.1);
    transition: all 0.3s ease;
}

.section-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #2d2d2d;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title::before {
    font-size: 24px;
}

/* Enhanced Stock Items */
.stock-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    border-radius: 10px;
    transition: all 0.3s;
    border: 1px solid #e5e7eb;
    position: relative;
}

.stock-item::before {
    content: '📦';
    font-size: 20px;
    margin-right: 12px;
}

.stock-item:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stock-item-name {
    font-weight: 600;
    color: #2d2d2d;
    display: flex;
    align-items: center;
    gap: 10px;
}

.stock-badge {
    background: linear-gradient(135deg, #ef4444, #f87171);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    display: flex;
    align-items: center;
    gap: 5px;
}

.stock-badge::before {
    content: '⚠️';
    font-size: 14px;
}

/* Enhanced Customer Items */
.customer-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    border-radius: 10px;
    transition: all 0.3s;
    border: 1px solid #e5e7eb;
}

.customer-item:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.customer-name {
    font-weight: 600;
    color: #2d2d2d;
    display: flex;
    align-items: center;
    gap: 10px;
}

.customer-name::before {
    content: '👤';
    font-size: 18px;
}

.customer-points {
    background: linear-gradient(135deg, #638ECB, #82A8D8);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(99, 142, 203, 0.3);
    display: flex;
    align-items: center;
    gap: 5px;
}

.customer-points::before {
    content: '🏆';
    font-size: 14px;
}

/* Enhanced Feedback Items */
.feedback-item {
    padding: 18px;
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    border-radius: 10px;
    border-left: 4px solid #638ECB;
    transition: all 0.3s;
    border: 1px solid #e5e7eb;
    position: relative;
}

.feedback-item::before {
    content: '💬';
    position: absolute;
    top: 18px;
    right: 18px;
    font-size: 24px;
    opacity: 0.3;
}

.feedback-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-left-color: #4A6FA5;
}

.customer-name-fb {
    font-weight: 700;
    color: #2d2d2d;
    display: flex;
    align-items: center;
    gap: 8px;
}

.customer-name-fb::before {
    content: '👤';
    font-size: 16px;
}

.feedback-text {
    color: #555;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 10px;
    padding-left: 24px;
}

.rating {
    font-size: 16px;
    padding-left: 24px;
}

/* Enhanced Chart Section */
.chart-section {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-top: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border: 1px solid rgba(99, 142, 203, 0.1);
}

.chart-title {
    font-size: 22px;
    font-weight: 700;
    color: #2d2d2d;
    display: flex;
    align-items: center;
    gap: 12px;
}

.chart-stat-box {
    background: linear-gradient(135deg, #638ECB 0%, #4A6FA5 100%);
    padding: 20px;
    border-radius: 12px;
    color: white;
    text-align: center;
    box-shadow: 0 4px 12px rgba(99, 142, 203, 0.3);
    transition: all 0.3s ease;
}

.chart-stat-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(99, 142, 203, 0.4);
}

.chart-stat-box.positive {
    background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.chart-stat-box.positive:hover {
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
}

.chart-stat-box.negative {
    background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.chart-stat-box.negative:hover {
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
}

.chart-stat-box.average {
    background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.chart-stat-box.average:hover {
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
}

.chart-stat-value {
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 8px;
}

.chart-insights {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 20px;
    border-radius: 12px;
    margin-top: 20px;
    border: 1px solid #e5e7eb;
}

.insight-title {
    font-weight: 700;
    color: #2d2d2d;
    margin-bottom: 15px;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.insight-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 14px;
    color: #555;
    padding: 10px;
    background: white;
    border-radius: 8px;
    margin-bottom: 8px;
    border-left: 3px solid #638ECB;
    transition: all 0.3s;
}

.insight-item:hover {
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.insight-icon {
    font-size: 20px;
    flex-shrink: 0;
}

/* Empty State Styling */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.empty-state::before {
    content: '📭';
    font-size: 48px;
    display: block;
    margin-bottom: 15px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .stat-box-content {
        flex-direction: column;
        text-align: center;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 24px;
    }
    
    .stat-value {
        font-size: 28px;
    }
}
        .bottom-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d2d2d;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #638ECB;
        }

        .stock-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .stock-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .stock-item:hover {
            background: #e9ecef;
        }

        .stock-item-name {
            font-weight: 500;
            color: #2d2d2d;
        }

        .stock-badge {
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .customer-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .customer-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .customer-item:hover {
            background: #e9ecef;
        }

        .customer-name {
            font-weight: 500;
            color: #2d2d2d;
        }

        .customer-points {
            background: #638ECB;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .feedback-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .feedback-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #638ECB;
        }

        .feedback-header {
            margin-bottom: 8px;
        }

        .customer-name-fb {
            font-weight: 600;
            color: #2d2d2d;
        }

        .feedback-text {
            color: #555;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .rating {
            font-size: 14px;
        }

        .chart-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .chart-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d2d2d;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-subtitle {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }
        
        .chart-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .chart-stat-box {
            background: linear-gradient(135deg, #638ECB 0%, #4A6FA5 100%);
            padding: 15px;
            border-radius: 8px;
            color: white;
            text-align: center;
        }
        
        .chart-stat-box.positive {
            background: linear-gradient(135deg, #638ECB 0%, #82A8D8 100%);
        }
        
        .chart-stat-box.negative {
            background: linear-gradient(135deg, #4A6FA5 0%, #638ECB 100%);
        }
        
        .chart-stat-box.average {
            background: linear-gradient(135deg, #82A8D8 0%, #638ECB 100%);
        }
        
        .chart-stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .chart-stat-label {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .legend-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 3px;
        }
        
        .chart-insights {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .insight-title {
            font-weight: 600;
            color: #2d2d2d;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .insight-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .insight-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #555;
        }
        
        .insight-icon {
            font-size: 16px;
        }

        .quick-stat {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }

        .quick-stat-icon {
            font-size: 24px;
        }

        .quick-stat-info {
            flex: 1;
        }

        .quick-stat-label {
            font-size: 12px;
            color: #666;
        }

        .quick-stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #2d2d2d;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            Panyeros kusina
        </div>
        <ul class="nav-menu">
            <li class="nav-item active">
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
                🏠 Dashboard
            </h1>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </div>
        
       <div class="stats-bar">
    <div class="stat-box">
        <div class="stat-box-content">
            <div class="stat-icon">📦</div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $total_items; ?></div>
                <div class="stat-label">Total Inventory Items</div>
                <div class="stat-sublabel">Active items in stock</div>
            </div>
        </div>
    </div>
    <div class="stat-box alert">
        <div class="stat-box-content">
            <div class="stat-icon">⚠️</div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $low_stock_count; ?></div>
                <div class="stat-label">Low Stock Alert</div>
                <div class="stat-sublabel">Items need restocking</div>
            </div>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-box-content">
            <div class="stat-icon">🏆</div>
            <div class="stat-info">
                <div class="stat-value"><?php echo number_format($total_points); ?></div>
                <div class="stat-label">Total Loyalty Points</div>
                <div class="stat-sublabel"><?php echo $customer_count; ?> total customers</div>
            </div>
        </div>
    </div>
</div>
            
            <div class="bottom-section">
                <div class="section-card">
                    <div class="section-title">🔴 Low Stock Items</div>
                    <div class="stock-list">
                        <?php if (empty($low_stock_items)): ?>
                            <p style="padding: 10px; color: #666;">No low stock items. Good job!</p>
                        <?php else: ?>
                            <?php foreach ($low_stock_items as $item): ?>
                                <div class="stock-item">
                                    <span class="stock-item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                    <span class="stock-badge"><?php echo $item['stocks']; ?> left</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="section-card">
                    <div class="section-title">🏆 Top Loyalty Customers</div>
                    <div class="customer-list">
                        <?php if (empty($top_customers)): ?>
                            <p style="padding: 10px; color: #666;">No customer data found.</p>
                        <?php else: ?>
                            <?php $rank = 1; ?>
                            <?php foreach ($top_customers as $customer): ?>
                                <div class="customer-item">
                                    <span class="customer-name"><?php echo $rank++; ?>. <?php echo htmlspecialchars($customer['name']); ?></span>
                                    <span class="customer-points"><?php echo $customer['points']; ?> pts</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="section-card" style="margin-top: 20px;">
                <div class="section-title">💬 Recent Feedback</div>
                <div class="feedback-list">
                    <?php if (empty($recent_feedback)): ?>
                        <p style="padding: 10px; color: #666;">No recent feedback.</p>
                    <?php else: ?>
                        <?php foreach ($recent_feedback as $feedback): ?>
                            <div class="feedback-item">
                                <div class="feedback-header">
                                    <span class="customer-name-fb"><?php echo htmlspecialchars($feedback['name']); ?></span>
                                </div>
                                <div class="feedback-text"><?php echo htmlspecialchars($feedback['comment']); ?></div>
                                <div class="rating"><?php echo str_repeat('⭐', (int)$feedback['rating']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Feedback Chart Section -->
            <div class="chart-section">
                <div class="chart-header">
                    <div>
                        <div class="chart-title">
                            📊 Monthly Feedback Trends
                        </div>
                        <div class="chart-subtitle">Track customer satisfaction over the last 12 months</div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <canvas id="feedbackChart"></canvas>
                </div>
                
                <div class="legend-container">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #4ade80;"></div>
                        <span>Positive Feedback (3-5 ⭐)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f87171;"></div>
                        <span>Negative Feedback (1-2 ⭐)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #60a5fa;"></div>
                        <span>Average Rating</span>
                    </div>
                </div>
                
                <?php
                // Calculate insights
                $total_feedback_count = array_sum(array_column($monthly_feedback, 'total_count'));
                $total_positive = array_sum(array_column($monthly_feedback, 'positive_count'));
                $total_negative = array_sum(array_column($monthly_feedback, 'negative_count'));
                $overall_avg = $total_feedback_count > 0 ? array_sum(array_column($monthly_feedback, 'avg_rating')) / count($monthly_feedback) : 0;
                
                // Find best and worst months
                $best_month = null;
                $worst_month = null;
                $highest_avg = 0;
                $lowest_avg = 5;
                
                foreach ($monthly_feedback as $month_data) {
                    if ($month_data['avg_rating'] > $highest_avg) {
                        $highest_avg = $month_data['avg_rating'];
                        $best_month = $month_data['month_label'];
                    }
                    if ($month_data['avg_rating'] < $lowest_avg && $month_data['total_count'] > 0) {
                        $lowest_avg = $month_data['avg_rating'];
                        $worst_month = $month_data['month_label'];
                    }
                }
                ?>
                
                <div class="chart-stats">
                    <div class="chart-stat-box">
                        <div class="chart-stat-value"><?php echo $total_feedback_count; ?></div>
                        <div class="chart-stat-label">Total Feedback</div>
                    </div>
                    <div class="chart-stat-box positive">
                        <div class="chart-stat-value"><?php echo $total_positive; ?></div>
                        <div class="chart-stat-label">Positive Reviews</div>
                    </div>
                    <div class="chart-stat-box negative">
                        <div class="chart-stat-value"><?php echo $total_negative; ?></div>
                        <div class="chart-stat-label">Negative Reviews</div>
                    </div>
                    <div class="chart-stat-box average">
                        <div class="chart-stat-value"><?php echo number_format($overall_avg, 2); ?></div>
                        <div class="chart-stat-label">Average Rating</div>
                    </div>
                </div>
                
                <?php if ($best_month || $worst_month): ?>
                <div class="chart-insights">
                    <div class="insight-title">📈 Key Insights</div>
                    <div class="insight-list">
                        <?php if ($best_month): ?>
                        <div class="insight-item">
                            <span class="insight-icon">🏆</span>
                            <span><strong>Best Month:</strong> <?php echo $best_month; ?> with <?php echo number_format($highest_avg, 2); ?> average rating</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($worst_month && $worst_month != $best_month): ?>
                        <div class="insight-item">
                            <span class="insight-icon">⚠️</span>
                            <span><strong>Needs Improvement:</strong> <?php echo $worst_month; ?> with <?php echo number_format($lowest_avg, 2); ?> average rating</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        $positive_percentage = $total_feedback_count > 0 ? ($total_positive / $total_feedback_count) * 100 : 0;
                        ?>
                        <div class="insight-item">
                            <span class="insight-icon">✅</span>
                            <span><strong>Satisfaction Rate:</strong> <?php echo number_format($positive_percentage, 1); ?>% positive feedback</span>
                        </div>
                        
                        <?php if ($positive_percentage >= 80): ?>
                        <div class="insight-item">
                            <span class="insight-icon">🎉</span>
                            <span><strong>Excellent Performance!</strong> You're maintaining high customer satisfaction</span>
                        </div>
                        <?php elseif ($positive_percentage < 60): ?>
                        <div class="insight-item">
                            <span class="insight-icon">💡</span>
                            <span><strong>Action Needed:</strong> Consider addressing customer concerns to improve ratings</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Prepare data for Chart.js
        const feedbackData = <?php echo json_encode($monthly_feedback); ?>;
        
        const labels = feedbackData.map(d => d.month_label);
        const positiveData = feedbackData.map(d => parseInt(d.positive_count));
        const negativeData = feedbackData.map(d => parseInt(d.negative_count));
        const avgRatingData = feedbackData.map(d => parseFloat(d.avg_rating));
        
        // Create the chart
        const ctx = document.getElementById('feedbackChart').getContext('2d');
        const feedbackChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Positive Feedback',
                        data: positiveData,
                        backgroundColor: 'rgba(74, 222, 128, 0.8)',
                        borderColor: 'rgba(34, 197, 94, 1)',
                        borderWidth: 2,
                        borderRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Negative Feedback',
                        data: negativeData,
                        backgroundColor: 'rgba(248, 113, 113, 0.8)',
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 2,
                        borderRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Average Rating',
                        data: avgRatingData,
                        type: 'line',
                        borderColor: 'rgba(96, 165, 250, 1)',
                        backgroundColor: 'rgba(96, 165, 250, 0.1)',
                        borderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y1',
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.yAxisID === 'y1') {
                                    label += context.parsed.y.toFixed(2) + ' ⭐';
                                } else {
                                    label += context.parsed.y + ' feedback';
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Feedback',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        min: 0,
                        max: 5,
                        title: {
                            display: true,
                            text: 'Average Rating (⭐)',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>