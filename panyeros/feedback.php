<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback - Panyeros sa Kusina</title>
    <link rel="stylesheet" href="feed.css">
  
</head>
<body>
    <!-- Sidebar -->
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
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-header-title">
                Customer Feedback
                <span class="feedback-icon">💬</span>
            </h1>
            <button class="logout-btn" onclick="alert('Logout functionality')">Logout</button>
        </div>
        
        <div class="content-area">
            <div class="container">
                <!-- Submit Feedback Card -->
                <div class="card">
                    <div class="card-header">Submit New Feedback</div>
                    <div class="card-body">
                        <div id="successAlert" class="alert alert-success">
                            Feedback submitted successfully!
                        </div>
                        
                        <form id="feedbackForm">
                            <div class="form-group">
                                <label class="form-label">Customer Name</label>
                                <input type="text" id="customerName" class="form-control" 
                                       placeholder="Enter your name" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Comment</label>
                                <textarea id="comment" class="form-control" 
                                          placeholder="Share your experience with us..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Rate us</label>
                                <div class="rating-container">
                                    <span class="star-display">⭐ ⭐ ⭐ ⭐ ⭐</span>
                                </div>
                                <select id="rating" class="rating-select" required>
                                    <option value="">Select Rating</option>
                                    <option value="5">5 Stars - Excellent</option>
                                    <option value="4">4 Stars - Very Good</option>
                                    <option value="3">3 Stars - Good</option>
                                    <option value="2">2 Stars - Fair</option>
                                    <option value="1">1 Star - Poor</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="submit-btn">Submit</button>
                        </form>
                    </div>
                </div>
                
                <!-- All Feedback Card -->
                <div class="card">
                    <div class="card-header">All Feedback</div>
                    <div class="card-body">
                        <div id="feedbackList" class="feedback-list">
                            <!-- Sample feedbacks -->
                            <div class="feedback-item">
                                <div class="feedback-header">
                                    <span class="customer-name">Anna Lee</span>
                                    <span class="feedback-date">Oct 12, 2025</span>
                                </div>
                                <div class="feedback-text">Great food and excellent service! Will definitely come back.</div>
                                <div class="feedback-rating">⭐⭐⭐⭐⭐</div>
                            </div>
                            
                            <div class="feedback-item">
                                <div class="feedback-header">
                                    <span class="customer-name">Mark Johnson</span>
                                    <span class="feedback-date">Oct 11, 2025</span>
                                </div>
                                <div class="feedback-text">The noodles are amazing! Very authentic taste.</div>
                                <div class="feedback-rating">⭐⭐⭐⭐</div>
                            </div>
                            
                            <div class="feedback-item">
                                <div class="feedback-header">
                                    <span class="customer-name">Lisa Wong</span>
                                    <span class="feedback-date">Oct 10, 2025</span>
                                </div>
                                <div class="feedback-text">Good food but waiting time is a bit long.</div>
                                <div class="feedback-rating">⭐⭐⭐</div>
                            </div>
                            
                            <div class="feedback-item">
                                <div class="feedback-header">
                                    <span class="customer-name">Robert Chen</span>
                                    <span class="feedback-date">Oct 09, 2025</span>
                                </div>
                                <div class="feedback-text">Best Filipino food in town! Highly recommended.</div>
                                <div class="feedback-rating">⭐⭐⭐⭐⭐</div>
                            </div>
                            
                            <div class="feedback-item">
                                <div class="feedback-header">
                                    <span class="customer-name">Sarah Martinez</span>
                                    <span class="feedback-date">Oct 08, 2025</span>
                                </div>
                                <div class="feedback-text">Delicious dishes and friendly staff. Will order again!</div>
                                <div class="feedback-rating">⭐⭐⭐⭐⭐</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Handle form submission
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const customerName = document.getElementById('customerName').value;
            const comment = document.getElementById('comment').value;
            const rating = parseInt(document.getElementById('rating').value);
            
            if (!customerName || !comment || !rating) {
                alert('Please fill in all fields');
                return;
            }
            
            // Create stars
            const stars = '⭐'.repeat(rating);
            
            // Get current date
            const currentDate = new Date().toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            });
            
            // Create new feedback item
            const feedbackHTML = `
                <div class="feedback-item" style="animation: slideIn 0.3s;">
                    <div class="feedback-header">
                        <span class="customer-name">${customerName}</span>
                        <span class="feedback-date">${currentDate}</span>
                    </div>
                    <div class="feedback-text">${comment}</div>
                    <div class="feedback-rating">${stars}</div>
                </div>
            `;
            
            // Add to the top of feedback list
            const feedbackList = document.getElementById('feedbackList');
            feedbackList.insertAdjacentHTML('afterbegin', feedbackHTML);
            
            // Show success message
            const successAlert = document.getElementById('successAlert');
            successAlert.classList.add('show');
            
            // Reset form
            document.getElementById('feedbackForm').reset();
            
            // Hide success message after 3 seconds
            setTimeout(() => {
                successAlert.classList.remove('show');
            }, 3000);
        });
    </script>
</body>
</html>