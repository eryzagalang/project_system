<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Panyeros Kusina</title>
   <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Sidebar -->
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
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="user-info">
                <span>👋 Welcome, <strong>Admin User</strong></span>
            </div>
            <button class="logout-btn" onclick="alert('Logout functionality')">Logout</button>
        </div>
        
        <div class="content-area">
            <h1 class="page-title">Dashboard</h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Inventory Items</div>
                        <div class="stat-icon">📦</div>
                    </div>
                    <div class="stat-value">50</div>
                    <div class="stat-label">Active items in stock</div>
                </div>
                
                <div class="stat-card alert-card">
                    <div class="stat-header">
                        <div class="stat-title">Low Stock Alert</div>
                        <div class="stat-icon">⚠️</div>
                    </div>
                    <div class="stat-value">5</div>
                    <div class="stat-label">Items need restocking</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Loyalty Points Issued</div>
                        <div class="stat-icon">⭐</div>
                    </div>
                    <div class="stat-value">1,450</div>
                    <div class="quick-stat">
                        <span class="quick-stat-icon">👥</span>
                        <div class="quick-stat-info">
                            <div class="quick-stat-label">Total Customers</div>
                            <div class="quick-stat-value">25</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bottom-section">
                <div class="section-card">
                    <div class="section-title">🔴 Low Stock Items</div>
                    <div class="stock-list">
                        <div class="stock-item">
                            <span class="stock-item-name">Rice (kg)</span>
                            <span class="stock-badge">5 left</span>
                        </div>
                        <div class="stock-item">
                            <span class="stock-item-name">Cooking Oil (L)</span>
                            <span class="stock-badge">3 left</span>
                        </div>
                        <div class="stock-item">
                            <span class="stock-item-name">Soy Sauce (bottle)</span>
                            <span class="stock-badge">7 left</span>
                        </div>
                        <div class="stock-item">
                            <span class="stock-item-name">Chicken (kg)</span>
                            <span class="stock-badge">2 left</span>
                        </div>
                        <div class="stock-item">
                            <span class="stock-item-name">Eggs (dozen)</span>
                            <span class="stock-badge">4 left</span>
                        </div>
                    </div>
                </div>
                
                <div class="section-card">
                    <div class="section-title">🏆 Top Loyalty Customers</div>
                    <div class="customer-list">
                        <div class="customer-item">
                            <span class="customer-name">1. Juan Dela Cruz</span>
                            <span class="customer-points">350 pts</span>
                        </div>
                        <div class="customer-item">
                            <span class="customer-name">2. Maria Santos</span>
                            <span class="customer-points">280 pts</span>
                        </div>
                        <div class="customer-item">
                            <span class="customer-name">3. Pedro Garcia</span>
                            <span class="customer-points">220 pts</span>
                        </div>
                        <div class="customer-item">
                            <span class="customer-name">4. Anna Lee</span>
                            <span class="customer-points">195 pts</span>
                        </div>
                        <div class="customer-item">
                            <span class="customer-name">5. Mark Johnson</span>
                            <span class="customer-points">180 pts</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="section-card" style="margin-top: 20px;">
                <div class="section-title">💬 Recent Feedback</div>
                <div class="feedback-list">
                    <div class="feedback-item">
                        <div class="feedback-header">
                            <span class="customer-name-fb">Anna Lee</span>
                            <span class="feedback-date">Oct 12, 2025</span>
                        </div>
                        <div class="feedback-text">Great food and excellent service! Will definitely come back.</div>
                        <div class="rating">⭐⭐⭐⭐⭐</div>
                    </div>
                    
                    <div class="feedback-item">
                        <div class="feedback-header">
                            <span class="customer-name-fb">Mark Johnson</span>
                            <span class="feedback-date">Oct 11, 2025</span>
                        </div>
                        <div class="feedback-text">The noodles are amazing! Very authentic taste.</div>
                        <div class="rating">⭐⭐⭐⭐</div>
                    </div>
                    
                    <div class="feedback-item">
                        <div class="feedback-header">
                            <span class="customer-name-fb">Lisa Wong</span>
                            <span class="feedback-date">Oct 10, 2025</span>
                        </div>
                        <div class="feedback-text">Good food but waiting time is a bit long.</div>
                        <div class="rating">⭐⭐⭐</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>