<?php
// faq.php - Public FAQ Page for Members/Customers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include base files - Adjusted paths for public directory
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../lib/base.php';

$db = db();

// Track FAQ views (increment when viewing details)
if (isset($_GET['id'])) {
    $faq_id = intval($_GET['id']);
    $stmt = $db->prepare("UPDATE faqs SET views = views + 1 WHERE faq_id = ?");
    $stmt->execute([$faq_id]);
}

// Get all active FAQs
$stmt = $db->query("SELECT * FROM faqs WHERE is_active = 1 ORDER BY category, helpful_count DESC");
$faqs = $stmt->fetchAll();

// Group FAQs by category
$faqs_by_category = [];
foreach ($faqs as $faq) {
    $category = $faq['category'] ?: 'General';
    $faqs_by_category[$category][] = $faq;
}

// Handle helpful/not helpful votes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote'])) {
    // AJAX voting is better for this — but for assignment, server-side is okay
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Please login to vote.';
    } else {
        $faq_id = intval($_POST['faq_id']);
        $vote_type = $_POST['vote']; // 'helpful' or 'not_helpful'
        
        // Simple voting — you should add a vote_logs table to prevent duplicate votes
        if ($vote_type === 'helpful') {
            $stmt = $db->prepare("UPDATE faqs SET helpful_count = helpful_count + 1 WHERE faq_id = ?");
        } else {
            $stmt = $db->prepare("UPDATE faqs SET not_helpful_count = not_helpful_count + 1 WHERE faq_id = ?");
        }
        
        $stmt->execute([$faq_id]);
        $_SESSION['message'] = 'Thank you for your feedback!';
        
        // Redirect to prevent form resubmission
        header('Location: faq.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Gadget Tech Store</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #5a4bff 0%, #3a2ee0 100%);
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 3rem;
            color: white;
            margin-bottom: 15px;
        }
        
        .header p {
            color: white;
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.9;
        }
        
        .categories-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .categories {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            justify-content: center;
        }
        
        .category-tag {
            padding: 10px 20px;
            background: #eef0ff;
            border-radius: 25px;
            text-decoration: none;
            color: #5a4bff;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .category-tag:hover {
            background: #5a4bff;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(90, 75, 255, 0.2);
        }
        
        .faq-section {
            margin-bottom: 40px;
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: 10px 10px 0 0;
            margin-bottom: 1px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .category-title {
            font-size: 1.8rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .faq-count {
            color: #333;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            background: #f5f5f5;
            border: 1px solid #ddd;
        }
        
        .faq-list {
            background: white;
            border-radius: 0 0 10px 10px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .faq-item {
            border-bottom: 1px solid #eee;
            padding: 0;
        }
        
        .faq-question {
            padding: 25px 30px;
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }
        
        .faq-question:hover {
            background: #f8f9ff;
        }
        
        .faq-question::after {
            content: '▼';
            font-size: 0.9rem;
            transition: transform 0.3s;
        }
        
        .faq-question.active::after {
            transform: rotate(180deg);
        }
        
        .faq-answer {
            padding: 0 30px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease-out;
            background: #f8f9ff;
            line-height: 1.6;
        }
        
        .faq-answer.active {
            padding: 25px 30px;
            max-height: 1000px;
        }
        
        .faq-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
            flex-wrap: wrap;
        }
        
        .vote-btn {
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: color 0.3s;
            padding: 5px 10px;
            border-radius: 5px;
        }
        
        .vote-btn:hover {
            color: #5a4bff;
            background: rgba(90, 75, 255, 0.1);
        }
        
        .views {
            color: #7f8c8d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .contact-support {
            background: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            margin-top: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .contact-support h2 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .contact-support p {
            color: #7f8c8d;
            margin-bottom: 25px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .btn-support {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(135deg, #5a4bff 0%, #3a2ee0 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .btn-support:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(90, 75, 255, 0.4);
        }
        
        .no-results {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 10px;
            color: #7f8c8d;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #5a4bff;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #4a3bdf;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(90, 75, 255, 0.2);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 2.2rem;
            }
            
            .category-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .faq-question {
                padding: 20px;
                font-size: 1.1rem;
            }
            
            .faq-answer.active {
                padding: 20px;
            }
            
            .faq-stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .categories {
                justify-content: flex-start;
            }
        }
        
        /* Add some animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .faq-item {
            animation: fadeIn 0.3s ease-out;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php 
    // Include header
    require_once __DIR__ . '/../includes/header.php'; 
    ?>
    
    <div class="container">
        <div class="header">
            <h1>❓ Frequently Asked Questions</h1>
            <p>Find quick answers to common questions about our Gadget Tech Store</p>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="categories-section">
            <h2 class="section-title">Browse by Category</h2>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Click on a category to jump to relevant FAQs</p>
            <div class="categories">
                <?php foreach (array_keys($faqs_by_category) as $category): ?>
                    <a href="#cat-<?php echo urlencode($category); ?>" class="category-tag">
                        <?php 
                        $icons = [
                            'Shipping' => '🚚',
                            'Returns' => '🔄',
                            'Payment' => '💳',
                            'Warranty' => '🔧',
                            'Technical' => '💻',
                            'General' => '📦',
                            'Order' => '📝',
                            'Product' => '📱',
                            'Account' => '👤'
                        ];
                        $icon = $icons[$category] ?? '❓';
                        echo $icon . ' ' . htmlspecialchars($category);
                        ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if (empty($faqs_by_category)): ?>
            <div class="no-results">
                <h3>No FAQs available at the moment.</h3>
                <p>Please check back later or contact support.</p>
            </div>
        <?php else: ?>
            <?php foreach ($faqs_by_category as $category => $category_faqs): ?>
                <div class="faq-section" id="cat-<?php echo urlencode($category); ?>">
                    <div class="category-header">
                        <div class="category-title">
                            <?php 
                            $icons = [
                                'Shipping' => '🚚',
                                'Returns' => '🔄',
                                'Payment' => '💳',
                                'Warranty' => '🔧',
                                'Technical' => '💻',
                                'General' => '📦',
                                'Order' => '📝',
                                'Product' => '📱',
                                'Account' => '👤'
                            ];
                            $icon = $icons[$category] ?? '❓';
                            echo $icon . ' ' . htmlspecialchars($category);
                            ?>
                            <span class="faq-count"><?php echo count($category_faqs); ?> FAQs</span>
                        </div>
                    </div>
                    
                    <div class="faq-list">
                        <?php foreach ($category_faqs as $faq): ?>
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFAQ(this)">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                </div>
                                <div class="faq-answer">
                                    <p><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                                    
                                    <div class="faq-stats">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="faq_id" value="<?php echo $faq['faq_id']; ?>">
                                            <button type="submit" name="vote" value="helpful" class="vote-btn">
                                                <i class="fas fa-thumbs-up"></i> Helpful (<?php echo $faq['helpful_count']; ?>)
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="faq_id" value="<?php echo $faq['faq_id']; ?>">
                                            <button type="submit" name="vote" value="not_helpful" class="vote-btn">
                                                <i class="fas fa-thumbs-down"></i> Not Helpful (<?php echo $faq['not_helpful_count']; ?>)
                                            </button>
                                        </form>
                                        
                                        <div class="views">
                                            <i class="fas fa-eye"></i> <?php echo $faq['views']; ?> views
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="contact-support">
            <h2>Still need help?</h2>
            <p>Can't find the answer you're looking for? Our support team is here to help you with any questions about our gadgets and tech products.</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="support/ticket_create.php" class="btn-support">
                    <i class="fas fa-ticket-alt"></i> Create Support Ticket
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-support">
                    <i class="fas fa-lock"></i> Login to Contact Support
                </a>
                <p style="margin-top: 15px; font-size: 0.9rem;">
                    Or <a href="contact.php" style="color: #5a4bff;">contact us directly</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    
    <script>
        // Toggle FAQ answer visibility
        function toggleFAQ(element) {
            const answer = element.nextElementSibling;
            element.classList.toggle('active');
            answer.classList.toggle('active');
        }
        
        // Smooth scroll to category when clicking on category tag
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scrolling for category links
            document.querySelectorAll('.category-tag').forEach(link => {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href.startsWith('#')) {
                        e.preventDefault();
                        const targetId = href.substring(1);
                        const targetElement = document.getElementById(targetId);
                        if (targetElement) {
                            window.scrollTo({
                                top: targetElement.offsetTop - 80,
                                behavior: 'smooth'
                            });
                        }
                    }
                });
            });
            
            // Expand FAQ if ID is in URL
            const urlParams = new URLSearchParams(window.location.search);
            const faqId = urlParams.get('id');
            if (faqId) {
                // You would need to add data-faq-id attributes to FAQ items
                // For now, we'll just scroll to the top
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        });
    </script>
</body>
</html>
