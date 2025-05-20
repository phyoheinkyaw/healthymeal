<?php
// Test file for direct file uploads
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { border: 1px solid #ccc; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .info { background-color: #e6f7ff; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type=file] { display: block; margin-bottom: 10px; }
        input[type=submit] { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        input[type=submit]:hover { background-color: #45a049; }
    </style>
</head>
<body>
    <div class="container">
        <h1>File Upload Test</h1>
        
        <div class="card">
            <h2>PHP Configuration</h2>
            <pre><?php
            echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
            echo "post_max_size: " . ini_get('post_max_size') . "\n";
            echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
            echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
            echo "memory_limit: " . ini_get('memory_limit') . "\n";
            echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "\n";
            ?></pre>
        </div>
        
        <div class="card">
            <h2>Upload Directory Check</h2>
            <?php
            $upload_dir = '../../uploads/payment_slips/';
            $real_path = realpath($upload_dir);
            echo "<p>Target directory: $upload_dir</p>";
            echo "<p>Real path: " . ($real_path ?: 'Not found') . "</p>";
            echo "<p>Directory exists: " . (is_dir($upload_dir) ? 'Yes' : 'No') . "</p>";
            
            if (is_dir($upload_dir)) {
                echo "<p>Directory is writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</p>";
                echo "<p>Directory permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "</p>";
                
                // Try to create a test file
                $test_file = $upload_dir . 'test_' . time() . '.txt';
                $write_test = @file_put_contents($test_file, 'Test file write permission');
                if ($write_test !== false) {
                    echo "<p class='success'>Successfully wrote test file: $test_file</p>";
                    @unlink($test_file); // Clean up
                } else {
                    echo "<p class='error'>Failed to write test file. Error: " . error_get_last()['message'] . "</p>";
                }
            } else {
                echo "<p class='error'>Upload directory does not exist or is not accessible</p>";
                echo "<p>Attempting to create directory...</p>";
                if (@mkdir($upload_dir, 0755, true)) {
                    echo "<p class='success'>Directory created successfully!</p>";
                } else {
                    echo "<p class='error'>Failed to create directory. Error: " . error_get_last()['message'] . "</p>";
                }
            }
            ?>
        </div>
        
        <div class="card">
            <h2>Simple File Upload Form</h2>
            <form action="test_file_upload.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="file">Select File:</label>
                    <input type="file" name="file" id="file">
                </div>
                <input type="hidden" name="test_type" value="simple">
                <input type="submit" value="Upload File">
            </form>
        </div>
        
        <div class="card">
            <h2>FormData Simulation (with JSON data)</h2>
            <form action="test_file_upload.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="transfer_slip">Payment Slip:</label>
                    <input type="file" name="transfer_slip" id="transfer_slip">
                </div>
                <div class="form-group">
                    <label for="transaction_id">Transaction ID:</label>
                    <input type="text" name="transaction_id" id="transaction_id" value="TEST-<?= time() ?>">
                </div>
                <div class="form-group">
                    <label for="order_data_text">Order Data (JSON):</label>
                    <textarea name="order_data_text" id="order_data_text" rows="5" style="width: 100%;">{
    "customer_id": 1,
    "payment_method": "KBZPay",
    "delivery_method": "Standard Delivery",
    "order_notes": "Test order"
}</textarea>
                </div>
                <input type="hidden" name="test_type" value="formdata">
                <input type="submit" value="Submit FormData">
            </form>
        </div>
        
        <?php
        // Process uploads
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo '<div class="card"><h2>Upload Results</h2>';
            
            // Display request info
            echo '<div class="info"><h3>Request Information</h3>';
            echo '<pre>';
            echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
            echo "CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set') . "\n";
            echo "POST Variables: " . print_r(array_keys($_POST), true) . "\n";
            echo "FILES Variables: " . print_r($_FILES, true) . "\n";
            echo '</pre></div>';
            
            $test_type = $_POST['test_type'] ?? 'unknown';
            
            if ($test_type === 'simple' && isset($_FILES['file'])) {
                processSimpleUpload($_FILES['file']);
            } else if ($test_type === 'formdata' && isset($_FILES['transfer_slip'])) {
                processFormDataUpload($_FILES['transfer_slip'], $_POST);
            }
            
            echo '</div>';
        }
        
        function processSimpleUpload($file) {
            echo '<h3>Simple File Upload Processing</h3>';
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/payment_slips/';
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $new_filename = 'test_' . uniqid() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                echo "<p>Temporary file: " . $file['tmp_name'] . "</p>";
                echo "<p>File exists: " . (file_exists($file['tmp_name']) ? 'Yes' : 'No') . "</p>";
                echo "<p>Is uploaded file: " . (is_uploaded_file($file['tmp_name']) ? 'Yes' : 'No') . "</p>";
                echo "<p>Target path: " . $upload_path . "</p>";
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    echo "<div class='success'>File uploaded successfully to: $upload_path</div>";
                    
                    // Display the file info
                    echo "<p>File size: " . filesize($upload_path) . " bytes</p>";
                    echo "<p>File type: " . mime_content_type($upload_path) . "</p>";
                    
                    // Show image preview if it's an image
                    $mime_type = mime_content_type($upload_path);
                    if (strpos($mime_type, 'image/') === 0) {
                        echo "<p>Image preview:</p>";
                        echo "<img src='../../uploads/payment_slips/$new_filename' style='max-width: 300px; max-height: 300px;'>";
                    }
                } else {
                    echo "<div class='error'>Failed to move uploaded file. Error: " . error_get_last()['message'] . "</div>";
                }
            } else {
                echo "<div class='error'>Upload error: " . uploadErrorToText($file['error']) . "</div>";
            }
        }
        
        function processFormDataUpload($file, $post_data) {
            echo '<h3>FormData Upload Processing</h3>';
            
            // Process the order data
            if (isset($post_data['order_data_text'])) {
                echo "<p>Order data provided as text field</p>";
                $order_data = json_decode($post_data['order_data_text'], true);
            } else if (isset($post_data['order_data'])) {
                echo "<p>Order data provided as POST field</p>";
                $order_data = json_decode($post_data['order_data'], true);
            } else {
                $order_data = null;
                echo "<div class='error'>No order data found in the request</div>";
            }
            
            echo "<p>Decoded order data:</p>";
            echo "<pre>" . ($order_data ? json_encode($order_data, JSON_PRETTY_PRINT) : 'None') . "</pre>";
            
            // Process transaction ID
            if (isset($post_data['transaction_id'])) {
                echo "<p>Transaction ID: " . htmlspecialchars($post_data['transaction_id']) . "</p>";
            } else {
                echo "<div class='error'>No transaction ID found in the request</div>";
            }
            
            // Process the file
            if ($file['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/payment_slips/';
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $new_filename = 'slip_' . uniqid() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                echo "<p>Temporary file: " . $file['tmp_name'] . "</p>";
                echo "<p>File exists: " . (file_exists($file['tmp_name']) ? 'Yes' : 'No') . "</p>";
                echo "<p>Is uploaded file: " . (is_uploaded_file($file['tmp_name']) ? 'Yes' : 'No') . "</p>";
                echo "<p>Target path: " . $upload_path . "</p>";
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    echo "<div class='success'>Payment slip uploaded successfully to: $upload_path</div>";
                    
                    // Display the file info
                    echo "<p>File size: " . filesize($upload_path) . " bytes</p>";
                    echo "<p>File type: " . mime_content_type($upload_path) . "</p>";
                    
                    // Show image preview if it's an image
                    $mime_type = mime_content_type($upload_path);
                    if (strpos($mime_type, 'image/') === 0) {
                        echo "<p>Image preview:</p>";
                        echo "<img src='../../uploads/payment_slips/$new_filename' style='max-width: 300px; max-height: 300px;'>";
                    }
                    
                    // Simulate successful API response
                    echo "<div class='success'>
                        <h4>Simulated API Response:</h4>
                        <pre>" . json_encode([
                            'success' => true,
                            'message' => 'Order placed successfully',
                            'order_id' => 'TEST-' . time(),
                            'transfer_slip' => 'uploads/payment_slips/' . $new_filename,
                            'transaction_id' => $post_data['transaction_id'] ?? 'UNKNOWN'
                        ], JSON_PRETTY_PRINT) . "</pre>
                    </div>";
                    
                } else {
                    echo "<div class='error'>Failed to move uploaded file. Error: " . error_get_last()['message'] . "</div>";
                }
            } else {
                echo "<div class='error'>Upload error: " . uploadErrorToText($file['error']) . "</div>";
            }
        }
        
        function uploadErrorToText($error_code) {
            switch ($error_code) {
                case UPLOAD_ERR_OK: return "No error, file uploaded successfully";
                case UPLOAD_ERR_INI_SIZE: return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                case UPLOAD_ERR_FORM_SIZE: return "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
                case UPLOAD_ERR_PARTIAL: return "The uploaded file was only partially uploaded";
                case UPLOAD_ERR_NO_FILE: return "No file was uploaded";
                case UPLOAD_ERR_NO_TMP_DIR: return "Missing a temporary folder";
                case UPLOAD_ERR_CANT_WRITE: return "Failed to write file to disk";
                case UPLOAD_ERR_EXTENSION: return "A PHP extension stopped the file upload";
                default: return "Unknown upload error code: " . $error_code;
            }
        }
        ?>
    </div>
</body>
</html> 