/**
 * Activity Chart Initialization
 * 
 * This script handles the initialization and data loading for the activity chart
 * on the admin dashboard and activity logs page.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize activity chart if element exists
    const chartElement = document.getElementById('activityChart');
    if (chartElement) {
        initActivityChart();
        
        // Chart period change event
        const periodSelector = document.getElementById('chart-period');
        if (periodSelector) {
            periodSelector.addEventListener('change', function() {
                initActivityChart();
            });
        }
    }
    
    // Load dashboard stats if elements exist
    if (document.getElementById('login-count') || 
        document.getElementById('failed-count') || 
        document.getElementById('book-count') || 
        document.getElementById('active-users')) {
        loadDashboardStats();
    }
});

/**
 * Load dashboard statistics from the server
 */
function loadDashboardStats() {
    fetch('activity_chart_data.php?type=dashboard_stats')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Update dashboard counters if elements exist
            if (document.getElementById('login-count')) {
                document.getElementById('login-count').textContent = data.login_count;
            }
            if (document.getElementById('failed-count')) {
                document.getElementById('failed-count').textContent = data.failed_count;
            }
            if (document.getElementById('book-count')) {
                document.getElementById('book-count').textContent = data.book_count;
            }
            if (document.getElementById('active-users')) {
                document.getElementById('active-users').textContent = data.active_users;
            }
            
            // Add animation effect
            document.querySelectorAll('.card-body h4').forEach(element => {
                element.classList.add('counter-animation');
                setTimeout(() => {
                    element.classList.remove('counter-animation');
                }, 1000);
            });
        })
        .catch(error => console.error('Error loading dashboard stats:', error));
}

/**
 * Initialize the activity chart with data from the server
 */
function initActivityChart() {
    const chartElement = document.getElementById('activityChart');
    if (!chartElement) return;
    
    const periodSelector = document.getElementById('chart-period');
    const days = periodSelector ? periodSelector.value : 30;
    
    // Show loading state
    chartElement.style.opacity = 0.5;
    
    fetch(`activity_chart_data.php?type=activity_chart&days=${days}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const ctx = chartElement.getContext('2d');
            
            // Destroy existing chart if it exists
            if (window.activityChart) {
                window.activityChart.destroy();
            }
            
            // Create new chart
            window.activityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Logins',
                            data: data.login_data,
                            borderColor: 'rgba(13, 110, 253, 1)',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Failed Attempts',
                            data: data.failed_data,
                            borderColor: 'rgba(220, 53, 69, 1)',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Book Activities',
                            data: data.book_data,
                            borderColor: 'rgba(25, 135, 84, 1)',
                            backgroundColor: 'rgba(25, 135, 84, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    family: "'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif",
                                    weight: 500
                                },
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: {
                                family: "'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif",
                                size: 14,
                                weight: 600
                            },
                            bodyFont: {
                                family: "'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif",
                                size: 13
                            },
                            padding: 12,
                            cornerRadius: 6,
                            caretSize: 6,
                            displayColors: true,
                            boxPadding: 4
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                font: {
                                    family: "'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif"
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    family: "'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif"
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
            
            // Remove loading state
            chartElement.style.opacity = 1;
        })
        .catch(error => {
            console.error('Error loading chart data:', error);
            chartElement.style.opacity = 1;
        });
}