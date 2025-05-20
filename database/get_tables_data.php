<?php
// Database connection
require_once '../config/connection.php';

// Get all tables in the database
$tablesQuery = "SHOW TABLES";
$tablesResult = $mysqli->query($tablesQuery);

$tableNames = [];

if ($tablesResult && $tablesResult->num_rows > 0) {
    // Collect table names
    while ($tableRow = $tablesResult->fetch_row()) {
        $tableName = $tableRow[0];
        $tableNames[] = $tableName;
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