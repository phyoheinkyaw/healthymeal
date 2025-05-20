<?php
// Display PHP information related to file uploads
echo "<h1>PHP File Upload Settings</h1>";
echo "<pre>";
echo "upload_max_filesize = " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size = " . ini_get('post_max_size') . "\n";
echo "max_file_uploads = " . ini_get('max_file_uploads') . "\n";
echo "memory_limit = " . ini_get('memory_limit') . "\n";
echo "max_execution_time = " . ini_get('max_execution_time') . "\n";
echo "display_errors = " . ini_get('display_errors') . "\n";
echo "file_uploads = " . ini_get('file_uploads') . "\n";
echo "upload_tmp_dir = " . ini_get('upload_tmp_dir') . "\n";
echo "</pre>";

// Display server information
echo "<h2>Server Information</h2>";
echo "<pre>";
echo "DOCUMENT_ROOT = " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "SCRIPT_FILENAME = " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "Current working directory = " . getcwd() . "\n";
echo "</pre>";

// Check uploads directory
$upload_dir = '../../uploads/payment_slips/';
$real_path = realpath($upload_dir);
echo "<h2>Uploads Directory Check</h2>";
echo "<pre>";
echo "Upload path: " . $upload_dir . "\n";
echo "Resolved path: " . $real_path . "\n";
echo "Directory exists: " . (is_dir($real_path) ? "Yes" : "No") . "\n";
if (is_dir($real_path)) {
    echo "Directory is writable: " . (is_writable($real_path) ? "Yes" : "No") . "\n";
    echo "Directory permissions: " . substr(sprintf('%o', fileperms($real_path)), -4) . "\n";
    
    // List files in directory
    echo "Files in directory:\n";
    $files = scandir($real_path);
    foreach ($files as $file) {
        if ($file != "." && $file != "..") {
            echo "- " . $file . "\n";
        }
    }
}
echo "</pre>";

// Display upload form
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo "<h2>Test File Upload</h2>";
    echo "<form action='' method='post' enctype='multipart/form-data'>";
    echo "<input type='file' name='test_file' id='test_file'>";
    echo "<br><br>";
    echo "<input type='submit' value='Upload'>";
    echo "</form>";
    
    echo "<h3>Advanced Test Form (Similar to Checkout)</h3>";
    echo "<form action='' method='post' enctype='multipart/form-data'>";
    echo "<input type='file' name='transfer_slip' id='transfer_slip'><br>";
    echo "<input type='hidden' name='transaction_id' value='TEST12345678901234'><br>";
    echo "<input type='hidden' name='order_data' value='{\"delivery_address\":\"Test Address\",\"customer_phone\":\"1234567890\",\"contact_number\":\"0987654321\",\"payment_method\":\"KBZPay\",\"payment_method_id\":\"1\"}'><br><br>";
    echo "<input type='submit' name='checkout_test' value='Test Checkout Upload'>";
    echo "</form>";
}

// Process upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Request Data</h2>";
    echo "<pre>";
    echo "POST data:\n";
    print_r($_POST);
    echo "\n\nFILES data:\n";
    print_r($_FILES);
    echo "</pre>";
    
    // Test checkout-like upload
    if (isset($_POST['checkout_test'])) {
        echo "<h2>Checkout Test Results</h2>";
        if (!isset($_FILES['transfer_slip'])) {
            echo "<p style='color: red;'>No transfer_slip file was submitted.</p>";
        } else {
            echo "<h3>Transfer Slip Details:</h3>";
            echo "<pre>";
            print_r($_FILES['transfer_slip']);
            echo "</pre>";
            
            if ($_FILES['transfer_slip']['error'] === UPLOAD_ERR_OK) {
                echo "<p style='color: green;'>File uploaded successfully!</p>";
                
                // Process exactly as in place_order.php
                $upload_dir = '../../uploads/payment_slips/';
                $filename = 'payment_' . uniqid() . '_' . time() . '_' . rand(1000, 9999);
                $ext = pathinfo($_FILES['transfer_slip']['name'], PATHINFO_EXTENSION);
                $filename .= '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                echo "<p>Attempting to move file to: $filepath</p>";
                
                if (move_uploaded_file($_FILES['transfer_slip']['tmp_name'], $filepath)) {
                    echo "<p style='color: green;'>File moved successfully!</p>";
                    echo "<p>File saved as: $filepath</p>";
                } else {
                    echo "<p style='color: red;'>Failed to move file.</p>";
                    $error = error_get_last();
                    if ($error) {
                        echo "<p>PHP Error: " . $error['message'] . "</p>";
                    }
                    
                    // Try copy as fallback
                    echo "<p>Trying direct copy as fallback...</p>";
                    if (copy($_FILES['transfer_slip']['tmp_name'], $filepath)) {
                        echo "<p style='color: green;'>File copied successfully!</p>";
                    } else {
                        echo "<p style='color: red;'>Copy also failed.</p>";
                    }
                }
            } else {
                echo "<p style='color: red;'>Upload Error Code: " . $_FILES['transfer_slip']['error'] . "</p>";
                // Error code explanations...
            }
        }
        
        echo "<h3>Transaction ID</h3>";
        echo "<p>Transaction ID from POST: " . (isset($_POST['transaction_id']) ? $_POST['transaction_id'] : 'Not found') . "</p>";
        
        echo "<h3>Order Data</h3>";
        if (isset($_POST['order_data'])) {
            $order_data = json_decode($_POST['order_data'], true);
            echo "<pre>";
            print_r($order_data);
            echo "</pre>";
        } else {
            echo "<p>No order_data found.</p>";
        }
    }
    
    // Regular test upload
    if (isset($_FILES['test_file'])) {
        echo "<h2>Regular Upload Results</h2>";
        
        echo "<h3>File Details:</h3>";
        echo "<pre>";
        print_r($_FILES['test_file']);
        echo "</pre>";
        
        if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
            echo "<p style='color: green;'>File uploaded successfully!</p>";
            
            // Check if we can get the file type
            echo "<h3>File Type Detection:</h3>";
            
            try {
                echo "<p>Testing finfo_file...</p>";
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['test_file']['tmp_name']);
                finfo_close($finfo);
                echo "<p>MIME type: $mime</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>Error with finfo_file: " . $e->getMessage() . "</p>";
            }
            
            // Test if we can move the file
            $upload_dir = '../../uploads/payment_slips/';
            $target_file = $upload_dir . 'test_upload_' . time() . '_' . basename($_FILES['test_file']['name']);
            
            echo "<h3>File Moving Test:</h3>";
            echo "<p>Attempting to move file to: $target_file</p>";
            
            if (move_uploaded_file($_FILES['test_file']['tmp_name'], $target_file)) {
                echo "<p style='color: green;'>File moved successfully!</p>";
                echo "<p>File saved as: $target_file</p>";
            } else {
                echo "<p style='color: red;'>Failed to move file.</p>";
                $error = error_get_last();
                if ($error) {
                    echo "<p>PHP Error: " . $error['message'] . "</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>Upload Error Code: " . $_FILES['test_file']['error'] . "</p>";
            
            switch ($_FILES['test_file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    echo "<p>The uploaded file exceeds the upload_max_filesize directive in php.ini.</p>";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    echo "<p>The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.</p>";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    echo "<p>The uploaded file was only partially uploaded.</p>";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    echo "<p>No file was uploaded.</p>";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    echo "<p>Missing a temporary folder.</p>";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    echo "<p>Failed to write file to disk.</p>";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    echo "<p>A PHP extension stopped the file upload.</p>";
                    break;
                default:
                    echo "<p>Unknown upload error.</p>";
            }
        }
    }
    
    // Check temporary directory
    echo "<h3>Temporary Directory Test:</h3>";
    $tmp_dir = sys_get_temp_dir();
    echo "<p>PHP temporary directory: $tmp_dir</p>";
    
    if (is_dir($tmp_dir) && is_writable($tmp_dir)) {
        echo "<p style='color: green;'>Temporary directory exists and is writable.</p>";
    } else {
        echo "<p style='color: red;'>Issue with temporary directory!</p>";
        echo "<p>Directory exists: " . (is_dir($tmp_dir) ? "Yes" : "No") . "</p>";
        echo "<p>Directory is writable: " . (is_writable($tmp_dir) ? "Yes" : "No") . "</p>";
    }
}
?> 