<?php
require_once '../includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// Redirect non-admin users
if (!$role || $role != 1) {
    header("Location: /hm/login.php");
    exit();
}

// Get admin data
$admin_id = $_SESSION['user_id'];
$result = $mysqli->query("SELECT full_name FROM users WHERE user_id = $admin_id");
$admin = $result->fetch_assoc();

// Get counts for dashboard
$users_count = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE role = 0")->fetch_assoc()['count'];
$orders_count = $mysqli->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$meal_kits_count = $mysqli->query("SELECT COUNT(*) as count FROM meal_kits")->fetch_assoc()['count'];
$ingredients_count = $mysqli->query("SELECT COUNT(*) as count FROM ingredients")->fetch_assoc()['count'];

// Get revenue metrics
$revenue_query = $mysqli->query("
    SELECT 
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as average_order_value,
        COALESCE(MAX(total_amount), 0) as highest_order,
        COUNT(CASE WHEN is_paid = 1 THEN 1 END) as paid_orders
    FROM orders
");
$revenue_metrics = $revenue_query->fetch_assoc();

// Get orders by status
$status_query = $mysqli->query("
    SELECT 
        os.status_name,
        COUNT(o.order_id) as count
    FROM orders o
    JOIN order_status os ON o.status_id = os.status_id
    GROUP BY o.status_id, os.status_name
    ORDER BY count DESC
");
$orders_by_status = [];
while ($row = $status_query->fetch_assoc()) {
    $orders_by_status[] = $row;
}

// Get most popular meal kits
$popular_meal_kits = $mysqli->query("
    SELECT 
        mk.name,
        COUNT(oi.order_item_id) as order_count
    FROM order_items oi
    JOIN meal_kits mk ON oi.meal_kit_id = mk.meal_kit_id
    GROUP BY oi.meal_kit_id
    ORDER BY order_count DESC
    LIMIT 5
");

// Get recent users
$recent_users = $mysqli->query("
    SELECT 
        user_id,
        full_name,
        email,
        created_at
    FROM users
    WHERE role = 0
    ORDER BY created_at DESC
    LIMIT 5
");

// Function to get monthly trends with a dynamic time range
function getMonthlyTrends($mysqli, $months = 6) {
    $query = $mysqli->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(order_id) as order_count,
            SUM(COALESCE(total_amount, 0)) as monthly_revenue
        FROM orders
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL $months MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    
    $labels = [];
    $order_data = [];
    $revenue_data = [];
    
    // Fill in any missing months with zeros to ensure continuous data
    $end_date = new DateTime();
    $start_date = clone $end_date;
    $start_date->modify("-$months months");
    
    // Create an array of all months in the range
    $all_months = [];
    $month_iterator = clone $start_date;
    while ($month_iterator <= $end_date) {
        $month_key = $month_iterator->format('Y-m');
        $all_months[$month_key] = [
            'label' => $month_iterator->format('M Y'),
            'order_count' => 0,
            'monthly_revenue' => 0
        ];
        $month_iterator->modify('+1 month');
    }
    
    // Fill in actual data where available
    while ($row = $query->fetch_assoc()) {
        $month_key = $row['month'];
        if (isset($all_months[$month_key])) {
            $all_months[$month_key]['order_count'] = (int)$row['order_count'];
            $all_months[$month_key]['monthly_revenue'] = (float)$row['monthly_revenue'];
        }
    }
    
    // Extract data in sequence
    foreach ($all_months as $month_data) {
        $labels[] = $month_data['label'];
        $order_data[] = $month_data['order_count'];
        $revenue_data[] = $month_data['monthly_revenue'];
    }
    
    return [
        'labels' => $labels,
        'order_data' => $order_data,
        'revenue_data' => $revenue_data
    ];
}

// Get 6-month trends by default
$monthly_data = getMonthlyTrends($mysqli, 6);
$trend_labels = $monthly_data['labels'];
$trend_order_data = $monthly_data['order_data'];
$trend_revenue_data = $monthly_data['revenue_data'];

// Also prepare 12-month data and all-time data for JavaScript
$yearly_data = getMonthlyTrends($mysqli, 12);
$all_time_query = $mysqli->query("
    SELECT 
        MIN(DATE_FORMAT(created_at, '%Y-%m-01')) as first_order_date
    FROM orders
    LIMIT 1
");
$first_order = $all_time_query->fetch_assoc();
$all_time_months = 6; // Default fallback
if ($first_order && !empty($first_order['first_order_date'])) {
    $first_date = new DateTime($first_order['first_order_date']);
    $now = new DateTime();
    $interval = $first_date->diff($now);
    $all_time_months = ($interval->y * 12) + $interval->m + 1; // +1 to include current month
}
$all_time_data = getMonthlyTrends($mysqli, max($all_time_months, 6));

// Get payment method distribution
$payment_methods = $mysqli->query("
    SELECT 
        ps.payment_method,
        COUNT(o.order_id) as count
    FROM orders o
    JOIN payment_settings ps ON o.payment_method_id = ps.id
    GROUP BY o.payment_method_id
    ORDER BY count DESC
");

// Get recent orders
$recent_orders = [];
$orders_result = $mysqli->query("
    SELECT 
        o.order_id,
        u.full_name as customer_name,
        o.created_at,
        os.status_name,
        COUNT(oi.order_item_id) as items_count,
        o.total_amount
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN order_status os ON o.status_id = os.status_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    GROUP BY o.order_id, u.full_name, o.created_at, os.status_name, o.total_amount
    ORDER BY o.created_at DESC
    LIMIT 5
");

if ($orders_result) {
    while ($row = $orders_result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

// Get today's orders count
$today_orders = $mysqli->query("
    SELECT COUNT(*) as count 
    FROM orders 
    WHERE DATE(created_at) = CURDATE()"
)->fetch_assoc()['count'];

// Get pending orders count
$pending_orders = $mysqli->query("
    SELECT COUNT(*) as count 
    FROM orders 
    WHERE status_id = 1"
)->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .stat-card .icon-background i {
            font-size: 4rem;
            opacity: 0.2;
        }
        
        .metric-card {
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
        }
        
        .metric-value {
            font-size: 1.75rem;
            font-weight: 600;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .chart-container {
            position: relative;
            height: 250px;
        }
    </style>
</head>

<body>

    <div class="overlay" onclick="toggleSidebar()"></div>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="sidebar-toggle">
            <button class="btn btn-dark" type="button" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <main class="main-content">
            <div class="container-fluid">
                <!-- Welcome Banner -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card bg-primary text-white">
                            <div class="card-body py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-1">Welcome back, <?php echo htmlspecialchars($admin['full_name']); ?>!</h4>
                                        <p class="mb-0">Here's what's happening with your store today.</p>
                                    </div>
                                    <div>
                                        <h5 class="mb-0"><i class="bi bi-calendar3"></i> <?php echo date('F d, Y'); ?></h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Today's Highlights -->
                <div class="row mb-4">
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="card stat-card primary-gradient text-white h-100">
                            <div class="card-body">
                                <h6 class="text-uppercase mb-2">Today's Orders</h6>
                                <h2 class="mb-0"><?php echo $today_orders; ?></h2>
                                <div class="icon-background">
                                    <i class="bi bi-cart3"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="card stat-card warning-gradient text-white h-100">
                            <div class="card-body">
                                <h6 class="text-uppercase mb-2">Pending Orders</h6>
                                <h2 class="mb-0"><?php echo $pending_orders; ?></h2>
                                <div class="icon-background">
                                    <i class="bi bi-hourglass-split"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="card stat-card success-gradient text-white h-100">
                            <div class="card-body">
                                <h6 class="text-uppercase mb-2">Total Revenue</h6>
                                <h2 class="mb-0"><?php echo number_format($revenue_metrics['total_revenue'], 0); ?> MMK</h2>
                                <div class="icon-background">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="card stat-card info-gradient text-white h-100">
                            <div class="card-body">
                                <h6 class="text-uppercase mb-2">Avg. Order Value</h6>
                                <h2 class="mb-0"><?php echo number_format($revenue_metrics['average_order_value'], 0); ?> MMK</h2>
                                <div class="icon-background">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Monthly Trends Chart -->
                    <div class="col-md-8 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Monthly Performance</h5>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary active" data-period="6">Last 6 Months</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="12">Last Year</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="all">All Time</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyTrendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Status Distribution -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Order Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="orderStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Metrics Row -->
                <div class="row mb-4">
                    <!-- Key Metrics -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Overall Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-6">
                                        <div class="metric-card p-3">
                                            <div class="metric-value"><?php echo $orders_count; ?></div>
                                            <div class="metric-label">Total Orders</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="metric-card p-3">
                                            <div class="metric-value"><?php echo $users_count; ?></div>
                                            <div class="metric-label">Customers</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="metric-card p-3">
                                            <div class="metric-value"><?php echo $revenue_metrics['paid_orders']; ?></div>
                                            <div class="metric-label">Completed Payments</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="metric-card p-3">
                                            <div class="metric-value"><?php echo number_format($revenue_metrics['highest_order'], 0); ?> MMK</div>
                                            <div class="metric-label">Highest Order</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Methods -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Payment Methods</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="paymentMethodsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Popular Meal Kits and Recent Users -->
                <div class="row mb-4">
                    <!-- Popular Meal Kits -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Most Popular Meal Kits</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th scope="col">Meal Kit</th>
                                                <th scope="col" class="text-end">Orders</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($kit = $popular_meal_kits->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($kit['name']); ?></td>
                                                <td class="text-end">
                                                    <span class="badge bg-success"><?php echo $kit['order_count']; ?></span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-white text-end">
                                <a href="meal-kits.php" class="btn btn-sm btn-outline-primary">View All Meal Kits</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Users -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Recent Customers</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th scope="col">Customer</th>
                                                <th scope="col">Email</th>
                                                <th scope="col" class="text-end">Joined</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($user = $recent_users->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td class="text-end">
                                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-white text-end">
                                <a href="users.php" class="btn btn-sm btn-outline-primary">View All Users</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Orders</h5>
                                <a href="orders.php" class="btn btn-sm btn-primary">View All Orders</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped" id="ordersTable">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Customer</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Total</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo $order['order_id']; ?></td>
                                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match(strtolower($order['status_name'])) {
                                                            'pending' => 'warning',
                                                            'processing', 'shipped' => 'info',
                                                            'delivered' => 'success',
                                                            'cancelled' => 'danger',
                                                            default => 'secondary'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst($order['status_name']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($order['total_amount'], 0); ?> MMK</td>
                                                <td>
                                                    <a href="order-details.php?id=<?php echo $order['order_id']; ?>"
                                                        class="btn btn-sm btn-outline-primary btn-ripple">
                                                        <i class="bi bi-eye"></i> View Details
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($recent_orders)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No recent orders found</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/admin.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Store chart data from PHP
            const chartData = {
                sixMonths: {
                    labels: <?php echo json_encode($trend_labels); ?>,
                    orderData: <?php echo json_encode($trend_order_data); ?>,
                    revenueData: <?php echo json_encode($trend_revenue_data); ?>
                },
                twelveMonths: {
                    labels: <?php echo json_encode($yearly_data['labels']); ?>,
                    orderData: <?php echo json_encode($yearly_data['order_data']); ?>,
                    revenueData: <?php echo json_encode($yearly_data['revenue_data']); ?>
                },
                allTime: {
                    labels: <?php echo json_encode($all_time_data['labels']); ?>,
                    orderData: <?php echo json_encode($all_time_data['order_data']); ?>,
                    revenueData: <?php echo json_encode($all_time_data['revenue_data']); ?>
                }
            };
            
            // Initialize the monthly trends chart
            const trendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
            const trendsChart = new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: chartData.sixMonths.labels,
                    datasets: [
                        {
                            label: 'Orders',
                            data: chartData.sixMonths.orderData,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.3,
                            borderWidth: 2,
                            fill: true,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Revenue (MMK)',
                            data: chartData.sixMonths.revenueData,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            tension: 0.3,
                            borderWidth: 2,
                            fill: false,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            grid: {
                                display: false
                            },
                            ticks: {
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Orders'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                display: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat().format(value) + ' MMK';
                                }
                            },
                            title: {
                                display: true,
                                text: 'Revenue'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.datasetIndex === 1) {
                                        label += new Intl.NumberFormat().format(context.raw) + ' MMK';
                                    } else {
                                        label += context.raw;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
            
            // Handle period change buttons
            const periodButtons = document.querySelectorAll('[data-period]');
            periodButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const period = this.getAttribute('data-period');
                    let data;
                    
                    // Remove active class from all buttons
                    periodButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Update chart with appropriate data
                    switch(period) {
                        case '6':
                            data = chartData.sixMonths;
                            break;
                        case '12':
                            data = chartData.twelveMonths;
                            break;
                        case 'all':
                            data = chartData.allTime;
                            break;
                        default:
                            data = chartData.sixMonths;
                    }
                    
                    // Update chart data
                    trendsChart.data.labels = data.labels;
                    trendsChart.data.datasets[0].data = data.orderData;
                    trendsChart.data.datasets[1].data = data.revenueData;
                    trendsChart.update();
                });
            });
            
            // Order Status Distribution Chart
            const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
            new Chart(orderStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        <?php 
                        foreach ($orders_by_status as $status) {
                            echo "'" . $status['status_name'] . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        data: [
                            <?php 
                            foreach ($orders_by_status as $status) {
                                echo $status['count'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: [
                            '#ffc107', // Warning (Pending)
                            '#17a2b8', // Info (Processing)
                            '#28a745', // Success (Delivered)
                            '#dc3545', // Danger (Cancelled)
                            '#6c757d', // Secondary (Others)
                            '#fd7e14'  // Orange (Additional status)
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });
            
            // Payment Methods Chart
            const paymentMethodsCtx = document.getElementById('paymentMethodsChart').getContext('2d');
            new Chart(paymentMethodsCtx, {
                type: 'pie',
                data: {
                    labels: [
                        <?php 
                        $payment_methods->data_seek(0);
                        while ($method = $payment_methods->fetch_assoc()) {
                            echo "'" . $method['payment_method'] . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        data: [
                            <?php 
                            $payment_methods->data_seek(0);
                            while ($method = $payment_methods->fetch_assoc()) {
                                echo $method['count'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: [
                            '#7CB9E8', // Blue
                            '#FF6B6B', // Red
                            '#6BCB77', // Green
                            '#FFD93D', // Yellow
                            '#B39DDB', // Purple
                            '#4D4D4D'  // Dark gray
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>