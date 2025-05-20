<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Healthy Meal Kit - Database Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Dark Theme Styling -->
    <style>
        :root {
            --primary: #5465ff;    /* Blue */
            --secondary: #788bff;  /* Lighter Blue */
            --accent: #50cc85;     /* Green accent */
            --dark-bg: #121212;
            --darker-bg: #1e1e1e;
            --card-bg: #2a2a2a;
            --light-text: #e0e0e0;
            --mid-text: #b0b0b0;
            --border-color: #3a3a3a;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 70px;
            background-color: var(--dark-bg);
            color: var(--light-text);
        }
        
        .table-responsive {
            margin-bottom: 2rem;
            background-color: var(--darker-bg);
            border-radius: 8px;
        }
        
        .table-name {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px 8px 0 0;
            position: relative;
            z-index: 1;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .table-section {
            margin-bottom: 40px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            background-color: var(--darker-bg);
        }
        
        /* Fixed action buttons */
        .action-buttons {
            position: fixed;
            bottom: 25px;
            left: 0;
            right: 0;
            z-index: 1500;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .action-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            border: none;
            transition: transform 0.2s, opacity 0.2s;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            opacity: 1;
        }
        
        #back-to-top {
            background-color: var(--primary);
            opacity: 0.8;
            display: none;
        }
        
        #refresh-btn {
            background-color: var(--accent);
            opacity: 0.8;
        }
        
        #recreate-db-btn-float {
            background-color: #dc3545; /* Bootstrap danger */
            opacity: 0.8;
        }
        
        .action-btn-label {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0,0,0,0.7);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.2s;
            pointer-events: none;
        }
        
        .action-btn:hover .action-btn-label {
            opacity: 1;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.75);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }
        
        .last-refresh-container {
            position: fixed;
            bottom: 85px;
            right: 25px;
            z-index: 1500;
            background-color: var(--card-bg);
            color: var(--light-text);
            padding: 8px 12px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            font-size: 12px;
        }
        
        .table-list-item {
            transition: all 0.2s ease;
            background-color: var(--card-bg);
            border-color: var(--border-color);
            margin-bottom: 1px;
        }
        
        .table-list-item:hover {
            background-color: rgba(84, 101, 255, 0.2);
        }
        
        .table-list-item a {
            color: var(--light-text);
            text-decoration: none;
            display: block;
            padding: 8px 10px;
        }
        
        .table-count {
            background-color: var(--primary);
            color: white;
            border-radius: 50px;
            padding: 2px 8px;
            font-size: 12px;
            margin-left: 8px;
        }
        
        /* Table styling */
        .table {
            color: var(--light-text);
            background-color: var(--darker-bg);
            border-color: var(--border-color);
            margin-bottom: 0;
        }
        
        /* Fix for white background in tables */
        .table tr, .table td, .table th {
            background-color: var(--darker-bg);
        }
        
        .table td, .table th {
            border-color: var(--border-color);
            vertical-align: middle;
            color: var(--light-text);
        }
        
        .table-striped>tbody>tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.03) !important;
            color: var(--light-text);
        }
        
        .table-striped>tbody>tr:nth-of-type(even) {
            background-color: var(--darker-bg) !important;
            color: var(--light-text);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(84, 101, 255, 0.1) !important;
            color: var(--light-text);
        }
        
        /* Fixed table headers */
        .table thead th {
            position: sticky;
            top: 0;
            background: var(--primary);
            color: white;
            z-index: 2;
            border-bottom: 2px solid var(--border-color);
        }
        
        /* For better JSON display */
        pre.json-display {
            background-color: var(--card-bg);
            border-radius: 4px;
            padding: 10px;
            max-height: 200px;
            overflow-y: auto;
            font-size: 12px;
            color: var(--light-text);
        }
        
        /* Animated spinner */
        .spinner {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Table of contents styling */
        .toc-card {
            position: sticky;
            top: 20px;
            background-color: var(--card-bg);
            border-color: var(--border-color);
        }
        
        .toc-card .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            font-weight: bold;
            border: none;
        }
        
        .toc-card .card-body, .toc-card .card-footer {
            background-color: var(--card-bg);
            border-color: var(--border-color);
        }
        
        /* Form controls */
        .form-control, .input-group-text {
            background-color: var(--darker-bg);
            border-color: var(--border-color);
            color: var(--light-text);
        }
        
        .form-control:focus {
            background-color: var(--darker-bg);
            border-color: var(--primary);
            color: var(--light-text);
            box-shadow: 0 0 0 0.25rem rgba(84, 101, 255, 0.25);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--darker-bg);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }
        
        /* Alerts */
        .alert-warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border-color: rgba(255, 193, 7, 0.2);
        }
        
        .alert-info {
            background-color: rgba(13, 202, 240, 0.1);
            color: #0dcaf0;
            border-color: rgba(13, 202, 240, 0.2);
        }
        
        /* Modal styling */
        .modal-content {
            background-color: var(--card-bg);
            color: var(--light-text);
            border-color: var(--border-color);
        }
        
        .modal-header {
            border-bottom-color: var(--border-color);
        }
        
        .modal-footer {
            border-top-color: var(--border-color);
        }
        
        /* Table list scrolling */
        #table-list {
            max-height: 60vh;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="loading-overlay">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-3 mb-4">
                <!-- Table of contents (stays fixed on scroll) -->
                <div class="card toc-card shadow-sm">
                    <div class="card-header">
                        <i class="bi bi-table me-2"></i>Database Tables
                    </div>
                    <div class="card-body p-0">
                        <div class="input-group p-3">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="search-tables" placeholder="Search tables...">
                        </div>
                        <ul class="list-group list-group-flush" id="table-list">
                            <!-- Tables will be listed here by PHP -->
                        </ul>
                    </div>
                    
                </div>
            </div>
            
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="mb-0">Healthy Meal Kit Database Viewer</h1>
                </div>
                
                <div class="alert alert-warning mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Warning:</strong> This page displays all database tables and their contents. 
                    Please delete this file after your project is complete for security reasons.
                </div>
                
                <div id="tables-container">
                    <?php
                    // Database connection
                    require_once '../config/connection.php';
                    
                    // Get all tables in the database
                    $tablesQuery = "SHOW TABLES";
                    $tablesResult = $mysqli->query($tablesQuery);
                    
                    $tableNames = [];
                    
                    if ($tablesResult && $tablesResult->num_rows > 0) {
                        // Collect table names for TOC
                        while ($tableRow = $tablesResult->fetch_row()) {
                            $tableName = $tableRow[0];
                            $tableNames[] = $tableName;
                            
                            // Get row count for each table
                            $countQuery = "SELECT COUNT(*) AS count FROM `$tableName`";
                            $countResult = $mysqli->query($countQuery);
                            $countRow = $countResult->fetch_assoc();
                            $rowCount = $countRow['count'];
                            
                            // Add to table list with row count
                            echo '<script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    var listItem = document.createElement("li");
                                    listItem.className = "table-list-item";
                                    listItem.innerHTML = \'<a href="#' . $tableName . '">' . $tableName . 
                                    ' <span class="table-count">' . $rowCount . '</span></a>\';
                                    document.getElementById("table-list").appendChild(listItem);
                                });
                            </script>';
                        }
                        
                        // Display each table
                        foreach ($tableNames as $tableName) {
                            echo '<div class="table-section" id="section-' . $tableName . '">';
                            echo '<h2 class="table-name" id="' . $tableName . '">' . $tableName . 
                                ' <small class="badge bg-light text-dark float-end">Rows: ';
                            
                            // Get row count
                            $countQuery = "SELECT COUNT(*) AS count FROM `$tableName`";
                            $countResult = $mysqli->query($countQuery);
                            $countRow = $countResult->fetch_assoc();
                            echo $countRow['count'] . '</small></h2>';
                            
                            // Get column information
                            $columnsQuery = "SHOW COLUMNS FROM `$tableName`";
                            $columnsResult = $mysqli->query($columnsQuery);
                            
                            if ($columnsResult && $columnsResult->num_rows > 0) {
                                $columns = [];
                                while ($columnRow = $columnsResult->fetch_assoc()) {
                                    $columns[] = [
                                        'name' => $columnRow['Field'],
                                        'type' => $columnRow['Type'],
                                        'key' => $columnRow['Key'],
                                        'null' => $columnRow['Null']
                                    ];
                                }
                                
                                // Get data from table
                                $dataQuery = "SELECT * FROM `$tableName` LIMIT 1000";
                                $dataResult = $mysqli->query($dataQuery);
                                
                                echo '<div class="table-responsive">';
                                echo '<table class="table table-striped table-bordered table-hover">';
                                
                                // Table header
                                echo '<thead class="custom-header">';
                                echo '<tr>';
                                foreach ($columns as $column) {
                                    echo '<th title="Type: ' . $column['type'] . 
                                        ($column['key'] ? ' | Key: ' . $column['key'] : '') . 
                                        ' | Null: ' . $column['null'] . '">' . 
                                        htmlspecialchars($column['name']) . '</th>';
                                }
                                echo '</tr>';
                                echo '</thead>';
                                
                                // Table body
                                echo '<tbody>';
                                if ($dataResult && $dataResult->num_rows > 0) {
                                    while ($dataRow = $dataResult->fetch_assoc()) {
                                        echo '<tr>';
                                        foreach ($columns as $column) {
                                            $colName = $column['name'];
                                            echo '<td>';
                                            
                                            // Handle JSON data
                                            if (isJson($dataRow[$colName])) {
                                                echo '<pre class="json-display">';
                                                echo json_encode(json_decode($dataRow[$colName]), JSON_PRETTY_PRINT);
                                                echo '</pre>';
                                            } 
                                            // Handle long text
                                            else if (is_string($dataRow[$colName]) && strlen($dataRow[$colName]) > 200) {
                                                echo '<div style="max-height: 200px; overflow-y: auto;">';
                                                echo nl2br(htmlspecialchars(substr($dataRow[$colName], 0, 200)) . '...');
                                                echo '</div>';
                                            }
                                            // Handle NULL values
                                            else if ($dataRow[$colName] === null) {
                                                echo '<em class="text-muted">NULL</em>';
                                            }
                                            // Handle image URLs
                                            else if (isImageUrl($dataRow[$colName])) {
                                                echo htmlspecialchars($dataRow[$colName]) . '<br>';
                                                echo '<a href="../' . $dataRow[$colName] . '" target="_blank">';
                                                echo '<img src="../' . $dataRow[$colName] . '" class="img-thumbnail" style="max-height: 50px;">';
                                                echo '</a>';
                                            }
                                            // Regular data
                                            else {
                                                echo htmlspecialchars($dataRow[$colName] ?? '');
                                            }
                                            
                                            echo '</td>';
                                        }
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="' . count($columns) . '" class="text-center">No data</td></tr>';
                                }
                                echo '</tbody>';
                                
                                echo '</table>';
                                echo '</div>';
                            } else {
                                echo '<div class="alert alert-info">No columns found in table ' . $tableName . '</div>';
                            }
                            echo '</div>'; // Close table-section
                        }
                    } else {
                        echo '<div class="alert alert-info">No tables found in database. Have you created the database yet?</div>';
                    }
                    
                    // Function to check if a string is valid JSON
                    function isJson($string) {
                        if (!is_string($string)) return false;
                        json_decode($string);
                        return (json_last_error() == JSON_ERROR_NONE);
                    }
                    
                    // Function to check if a string is likely an image URL
                    function isImageUrl($string) {
                        if (!is_string($string)) return false;
                        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                        foreach ($imageExtensions as $ext) {
                            if (stripos($string, '.' . $ext) !== false) {
                                return true;
                            }
                        }
                        return false;
                    }
                    
                    // Close the database connection
                    $mysqli->close();
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons (Centered at Bottom) -->
    <div class="action-buttons">
        <button id="back-to-top" class="action-btn" title="Back to Top">
            <i class="bi bi-arrow-up"></i>
            <span class="action-btn-label">Back to Top</span>
        </button>
        
        <button id="refresh-btn" class="action-btn" title="Refresh Data">
            <i class="bi bi-arrow-clockwise"></i>
            <span class="action-btn-label">Refresh Data</span>
        </button>
        
        <button id="recreate-db-btn-float" class="action-btn" title="Recreate Database">
            <i class="bi bi-database-fill-gear"></i>
            <span class="action-btn-label">Recreate Database</span>
        </button>
    </div>
    
    <!-- Last Refresh Time -->
    <div class="last-refresh-container">
        <i class="bi bi-clock"></i> Last refreshed: <span id="last-refresh-time">Just now</span>
    </div>
    
    <!-- Recreate Database Confirmation Modal -->
    <div class="modal fade" id="recreateDbModal" tabindex="-1" aria-labelledby="recreateDbModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                    <h5 class="modal-title" id="recreateDbModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                        Recreate Database
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This will completely recreate the database from scratch. All existing data will be lost!
                    </div>
                    <p>Are you sure you want to recreate the database?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-recreate-db">
                        <i class="bi bi-database-fill-x me-1"></i> Yes, Recreate Database
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Processing Modal -->
    <div class="modal fade" id="processingModal" tabindex="-1" aria-labelledby="processingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                    <h5 class="modal-title" id="processingModalLabel">
                        <i class="bi bi-database-fill-gear me-2"></i>
                        Processing
                    </h5>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="spinner-border text-primary mb-4" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Processing...</span>
                    </div>
                    <h5 class="mb-3">Recreating Database</h5>
                    <p class="mb-4">Please wait while we recreate your database. This may take a moment...</p>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" 
                             style="width: 0%; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap and jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Back to top button functionality
            const backToTopButton = document.getElementById("back-to-top");
            
            window.onscroll = function() {
                if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
                    backToTopButton.style.display = "flex";
                } else {
                    backToTopButton.style.display = "none";
                }
            };
            
            backToTopButton.addEventListener("click", function() {
                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
            });
            
            // Table search functionality
            $("#search-tables").on("keyup", function() {
                const value = $(this).val().toLowerCase();
                $("#table-list li").each(function() {
                    const $this = $(this);
                    const tableText = $this.text().toLowerCase();
                    const matchFound = tableText.indexOf(value) > -1;
                    $this.toggle(matchFound);
                });
                
                $(".table-section").each(function() {
                    const $this = $(this);
                    const tableName = $this.find(".table-name").text().toLowerCase();
                    const matchFound = tableName.indexOf(value) > -1;
                    $this.toggle(matchFound);
                });
            });
            
            // Refresh functionality
            $("#refresh-btn").click(refreshData);
            
            function refreshData() {
                showLoading();
                
                setTimeout(function() {
                    location.reload();
                }, 500);
            }
            
            function showLoading() {
                $(".loading-overlay").fadeIn(200);
            }
            
            function hideLoading() {
                $(".loading-overlay").fadeOut(200);
            }
            
            function updateLastRefreshTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                $("#last-refresh-time").text(timeString);
            }
            
            // Initialize last refresh time
            updateLastRefreshTime();
            
            // Database recreation functionality
            $("#recreate-db-btn-float").click(function() {
                $("#recreateDbModal").modal('show');
            });
            
            $("#confirm-recreate-db").click(function() {
                // Hide the confirmation modal
                $("#recreateDbModal").modal('hide');
                
                // Show the processing modal
                $("#processingModal").modal('show');
                
                // Set initial progress
                let progress = 10;
                $(".progress-bar").css("width", progress + "%");
                
                // Simulate progress until we get the real result
                const progressInterval = setInterval(function() {
                    progress += 5;
                    if (progress <= 90) {
                        $(".progress-bar").css("width", progress + "%");
                    }
                }, 300);
                
                // Execute the db_create.php script
                $.ajax({
                    url: "db_create.php",
                    type: "GET",
                    success: function(response) {
                        // Stop the progress interval
                        clearInterval(progressInterval);
                        
                        // Set progress to 100%
                        $(".progress-bar").css("width", "100%");
                        
                        // Wait a moment and then reload the page
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    },
                    error: function(xhr, status, error) {
                        // Stop the progress interval
                        clearInterval(progressInterval);
                        
                        // Hide the processing modal
                        $("#processingModal").modal('hide');
                        
                        // Show error alert
                        alert("Failed to recreate database: " + error);
                    }
                });
            });
        });
    </script>
</body>
</html> 