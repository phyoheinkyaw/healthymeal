<?php
/**
 * Update Helper - Fixes role check for all admin files
 * 
 * This script traverses the admin directory and updates all PHP files 
 * to use the new numeric values for roles (0: user, 1: admin)
 */

// Set the paths to scan
$paths = [
    __DIR__ . '/admin',
    __DIR__ . '/admin/api'
];

// Patterns to replace
$patterns = [
    // Role checks for string 'admin'
    '/\$role !== \'admin\'/' => '$role != 1',
    '/\$role === \'admin\'/' => '$role == 1',
    
    // Check in arrays and session checks
    '/\$_SESSION\[\'role\'\] !== \'admin\'/' => '$_SESSION[\'role\'] != 1',
    '/\$_SESSION\[\'role\'\] === \'admin\'/' => '$_SESSION[\'role\'] == 1',
    
    // SQL queries
    '/role = \'user\'/' => 'role = 0',
    '/role = \'admin\'/' => 'role = 1',
    
    // Role comparisons in forms and JS strings
    '/user.role === \'admin\'/' => 'user.role === 1 || user.role === \'admin\'',
    '/role === \'admin\'/' => 'role == 1'
];

// Counter for modified files
$modified_count = 0;

// Function to update file content
function updateFile($filePath, $patterns) {
    $content = file_get_contents($filePath);
    $original = $content;
    
    // Apply all replacements
    foreach ($patterns as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    // Only update if content changed
    if ($content !== $original) {
        file_put_contents($filePath, $content);
        echo "Updated: $filePath\n";
        return true;
    }
    
    return false;
}

// Recursive directory scanning function
function scanDirectory($path, $patterns, &$modified_count) {
    $items = glob($path . '/*');
    
    foreach ($items as $item) {
        if (is_dir($item)) {
            scanDirectory($item, $patterns, $modified_count);
        } else if (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
            if (updateFile($item, $patterns)) {
                $modified_count++;
            }
        }
    }
}

// Run the update process
echo "Starting admin role update process...\n";

foreach ($paths as $path) {
    scanDirectory($path, $patterns, $modified_count);
}

echo "Update complete. Modified $modified_count files.\n"; 