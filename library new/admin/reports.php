<?php
// Include necessary files
require_once "../config.php";
require_once "../admin_auth.php";

// Verify admin session
if (!verify_admin_session()) {
    header("Location: ../admin_login.php");
    exit;
}

// Log this page access
log_admin_activity($_SESSION["user_id"], 'reports_page_access', $conn);

// Initialize variables
$report_type = isset($_GET['type']) ? $_GET['type'] : 'borrowings';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$category = isset($_GET['category']) ? $_GET['category'] : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Get report data based on type
$report_data = [];
$chart_data = [];

switch ($report_type) {
    case 'borrowings':
        // Borrowings report
        $query = "SELECT DATE(b.borrow_date) as date, COUNT(*) as count 
                 FROM borrowings b
                 WHERE b.borrow_date BETWEEN ? AND ?
                 GROUP BY DATE(b.borrow_date)
                 ORDER BY date";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $chart_data[] = [
                'date' => $row['date'],
                'count' => $row['count']
            ];
        }
        $stmt->close();
        
        // Get detailed borrowings data
        $query = "SELECT b.*, u.username, u.full_name, bk.title as book_title, bk.isbn
                 FROM borrowings b
                 JOIN users u ON b.user_id = u.id
                 JOIN books bk ON b.book_id = bk.id
                 WHERE b.borrow_date BETWEEN ? AND ?
                 ORDER BY b.borrow_date DESC
                 LIMIT 100";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
        }
        $stmt->close();
        break;
        
    case 'popular_books':
        // Popular books report
        $query = "SELECT b.book_id, bk.title, bk.author, bk.isbn, bk.category, COUNT(*) as borrow_count
                 FROM borrowings b
                 JOIN books bk ON b.book_id = bk.id
                 WHERE b.borrow_date BETWEEN ? AND ?";
        
        $params = [$date_from, $date_to];
        $types = "ss";
        
        if (!empty($category)) {
            $query .= " AND bk.category = ?";
            $params[] = $category;
            $types .= "s";
        }
        
        $query .= " GROUP BY b.book_id
                   ORDER BY borrow_count DESC
                   LIMIT 20";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
            $chart_data[] = [
                'title' => $row['title'],
                'count' => $row['borrow_count']
            ];
        }
        $stmt->close();
        break;
        
    case 'active_users':
        // Active users report
        $query = "SELECT u.id, u.username, u.full_name, u.email, COUNT(b.id) as borrow_count
                 FROM users u
                 LEFT JOIN borrowings b ON u.id = b.user_id AND b.borrow_date BETWEEN ? AND ?
                 GROUP BY u.id
                 ORDER BY borrow_count DESC
                 LIMIT 20";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
            if ($row['borrow_count'] > 0) {
                $chart_data[] = [
                    'name' => $row['full_name'],
                    'count' => $row['borrow_count']
                ];
            }
        }
        $stmt->close();
        break;
        
    case 'overdue':
        // Overdue books report
        $query = "SELECT b.*, u.username, u.full_name, bk.title as book_title, bk.isbn,
                 DATEDIFF(CURRENT_DATE(), b.due_date) as days_overdue
                 FROM borrowings b
                 JOIN users u ON b.user_id = u.id
                 JOIN books bk ON b.book_id = bk.id
                 WHERE b.status = 'active' AND b.due_date < CURRENT_DATE()
                 ORDER BY days_overdue DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
        }
        $stmt->close();
        break;
        
    case 'user_history':
        // User borrowing history
        if ($user_id > 0) {
            $query = "SELECT b.*, bk.title as book_title, bk.isbn
                     FROM borrowings b
                     JOIN books bk ON b.book_id = bk.id
                     WHERE b.user_id = ? AND b.borrow_date BETWEEN ? AND ?
                     ORDER BY b.borrow_date DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iss", $user_id, $date_from, $date_to);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            $stmt->close();
            
            // Get user details
            $query = "SELECT * FROM users WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_details = $result->fetch_assoc();
            $stmt->close();
        }
        break;
}

// Get categories for filter
$categories = [];
$stmt = $conn->prepare("SELECT DISTINCT category FROM books WHERE category IS NOT NULL ORDER BY category");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $categories[] = $row['category'];
}
$stmt->close();

// Get users for filter
$users = [];
$stmt = $conn->prepare("SELECT id, username, full_name FROM users ORDER BY full_name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Include header
$page_title = "Reports";
include "../admin/includes/header.php";
?>

<div class="main-content">
    <div class="header">
        <h1><i class="fas fa-chart-bar"></i> Reports</h1>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2>Generate Report</h2>
            <div class="card-tools">
                <form method="GET" action="" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type">Report Type</label>
                            <select name="type" id="type" class="form-control" onchange="toggleFilters()">
                                <option value="borrowings" <?php echo ($report_type == 'borrowings') ? 'selected' : ''; ?>>Borrowings</option>
                                <option value="popular_books" <?php echo ($report_type == 'popular_books') ? 'selected' : ''; ?>>Popular Books</option>
                                <option value="active_users" <?php echo ($report_type == 'active_users') ? 'selected' : ''; ?>>Active Users</option>
                                <option value="overdue" <?php echo ($report_type == 'overdue') ? 'selected' : ''; ?>>Overdue Books</option>
                                <option value="user_history" <?php echo ($report_type == 'user_history') ? 'selected' : ''; ?>>User History</option>
                            </select>
                        </div>
                        
                        <div class="form-group date-filter">
                            <label for="date_from">From Date</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="form-group date-filter">
                            <label for="date_to">To Date</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="form-group category-filter" style="<?php echo ($report_type == 'popular_books') ? '' : 'display: none;'; ?>">
                            <label for="category">Category</label>
                            <select name="category" id="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category == $cat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group user-filter" style="<?php echo ($report_type == 'user_history') ? '' : 'display: none;'; ?>">
                            <label for="user_id">User</label>
                            <select name="user_id" id="user_id" class="form-control">
                                <option value="">Select User</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($user_id == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn-primary form-control">
                                <i class="fas fa-search"></i> Generate Report
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn-secondary form-control" onclick="exportReport()">
                                <i class="fas fa-download"></i> Export CSV
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card-body">
            <?php if ($report_type == 'borrowings'): ?>
                <h3>Borrowings Report (<?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?>)</h3>
                
                <div class="chart-container">
                    <canvas id="borrowingsChart"></canvas>
                </div>
                
                <div class="table-responsive mt-4">
                    <table class="table" id="reportTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Book</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($report_data)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No data found for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($report_data as $item): ?>
                                    <tr>
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['book_title']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($item['borrow_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($item['due_date'])); ?></td>
                                        <td><?php echo $item['return_date'] ? date('M d, Y', strtotime($item['return_date'])) : '-'; ?></td>
                                        <td>
                                            <span class="badge <?php echo ($item['status'] == 'returned') ? 'badge-success' : (($item['status'] == 'overdue') ? 'badge-danger' : 'badge-info'); ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($report_type == 'popular_books'): ?>
                <h3>Popular Books Report (<?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?>)</h3>
                
                <div class="chart-container">
                    <canvas id="popularBooksChart"></canvas>
                </div>
                
                <div class="table-responsive mt-4">
                    <table class="table" id="reportTable">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Category</th>
                                <th>Borrow Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($report_data)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No data found for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($report_data as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['title']); ?></td>
                                        <td><?php echo htmlspecialchars($item['author']); ?></td>
                                        <td><?php echo htmlspecialchars($item['isbn']); ?></td>
                                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                                        <td><?php echo $item['borrow_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($report_type == 'active_users'): ?>
                <h3>Active Users Report (<?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?>)</h3>
                
                <div class="chart-container">
                    <canvas id="activeUsersChart"></canvas>
                </div>
                
                <div class="table-responsive mt-4">
                    <table class="table" id="reportTable">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Borrowings Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($report_data)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No data found for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($report_data as $item): ?>
                                    <tr>
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['username']); ?></td>
                                        <td><?php echo htmlspecialchars($item['email']); ?></td>
                                        <td><?php echo $item['borrow_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($report_type == 'overdue'): ?>
                <h3>Overdue Books Report</h3>
                
                <div class="table-responsive">
                    <table class="table" id="reportTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Book</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                                <th>Fine Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($report_data)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No overdue books found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($report_data as $item): ?>
                                    <tr>
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['book_title']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($item['borrow_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($item['due_date'])); ?></td>
                                        <td><?php echo $item['days_overdue']; ?> days</td>
                                        <td>$<?php echo number_format($item['fine_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($report_type == 'user_history'): ?>
                <?php if ($user_id > 0 && isset($user_details)): ?>
                    <h3>Borrowing History for <?php echo htmlspecialchars($user_details['full_name']); ?></h3>
                    
                    <div class="user-details">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user_details['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user_details['email']); ?></p>
                        <p><strong>Period:</strong> <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></p>
                    </div>
                    
                    <div class="table-responsive mt-4">
                        <table class="table" id="reportTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Book</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($report_data)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No borrowing history found for this user</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($report_data as $item): ?>
                                        <tr>
                                            <td><?php echo $item['id']; ?></td>
                                            <td><?php echo htmlspecialchars($item['book_title']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($item['borrow_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($item['due_date'])); ?></td>
                                            <td><?php echo $item['return_date'] ? date('M d, Y', strtotime($item['return_date'])) : '-'; ?></td>
                                            <td>
                                                <span class="badge <?php echo ($item['status'] == 'returned') ? 'badge-success' : (($item['status'] == 'overdue') ? 'badge-danger' : 'badge-info'); ?>">
                                                    <?php echo ucfirst($item['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Please select a user to view their borrowing history.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .chart-container {
        height: 400px;
        margin-bottom: 30px;
    }
    
    .form-row {
        flex-wrap: wrap;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
    }
    
    .mt-4 {
        margin-top: 20px;
    }
    
    .user-details {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .user-details p {
        margin-bottom: 5px;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Toggle filters based on report type
    function toggleFilters() {
        const reportType = document.getElementById('type').value;
        
        // Show/hide date filters
        const dateFilters = document.querySelectorAll('.date-filter');
        dateFilters.forEach(filter => {
            filter.style.display = reportType === 'overdue' ? 'none' : '';
        });
        
        // Show/hide category filter
        const categoryFilter = document.querySelector('.category-filter');
        categoryFilter.style.display = reportType === 'popular_books' ? '' : 'none';
        
        // Show/hide user filter
        const userFilter = document.querySelector('.user-filter');
        userFilter.style.display = reportType === 'user_history' ? '' : 'none';
    }
    
    // Export report to CSV
    function exportReport() {
        const table = document.getElementById('reportTable');
        if (!table) return;
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                // Get text content and clean it
                let data = cols[j].textContent.replace(/(\r\n|\n|\r)/gm, '').trim();
                
                // Quote fields with commas
                if (data.includes(',')) {
                    data = `"${data}"`;
                }
                
                row.push(data);
            }
            
            csv.push(row.join(','));
        }
        
        // Download CSV file
        const csvString = csv.join('\n');
        const filename = '<?php echo $report_type; ?>_report_<?php echo date('Y-m-d'); ?>.csv';
        
        const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
        
        // Create download link
        const link = document.createElement('a');
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($report_type == 'borrowings' && !empty($chart_data)): ?>
            // Borrowings chart
            const borrowingsCtx = document.getElementById('borrowingsChart').getContext('2d');
            
            const dates = <?php echo json_encode(array_column($chart_data, 'date')); ?>;
            const counts = <?php echo json_encode(array_column($chart_data, 'count')); ?>;
            
            new Chart(borrowingsCtx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Number of Borrowings',
                        data: counts,
                        backgroundColor: 'rgba(74, 105, 189, 0.2)',
                        borderColor: 'rgba(74, 105, 189, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
        
        <?php if ($report_type == 'popular_books' && !empty($chart_data)): ?>
            // Popular books chart
            const booksCtx = document.getElementById('popularBooksChart').getContext('2d');
            
            const titles = <?php echo json_encode(array_column($chart_data, 'title')); ?>;
            const bookCounts = <?php echo json_encode(array_column($chart_data, 'count')); ?>;
            
            // Generate colors
            const backgroundColors = titles.map((_, i) => {
                const hue = (i * 25) % 360;
                return `hsla(${hue}, 70%, 60%, 0.7)`;
            });
            
            new Chart(booksCtx, {
                type: 'bar',
                data: {
                    labels: titles,
                    datasets: [{
                        label: 'Borrow Count',
                        data: bookCounts,
                        backgroundColor: backgroundColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            ticks: {
                                autoSkip: false,
                                maxRotation: 90,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
        
        <?php if ($report_type == 'active_users' && !empty($chart_data)): ?>
            // Active users chart
            const usersCtx = document.getElementById('activeUsersChart').getContext('2d');
            
            const names = <?php echo json_encode(array_column($chart_data, 'name')); ?>;
            const userCounts = <?php echo json_encode(array_column($chart_data, 'count')); ?>;
            
            // Generate colors
            const userColors = names.map((_, i) => {
                const hue = (i * 25) % 360;
                return `hsla(${hue}, 70%, 60%, 0.7)`;
            });
            
            new Chart(usersCtx, {
                type: 'bar',
                data: {
                    labels: names,
                    datasets: [{
                        label: 'Borrowings Count',
                        data: userCounts,
                        backgroundColor: userColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    });
</script>

<?php
// Include footer
include "../admin/includes/footer.php";
?>