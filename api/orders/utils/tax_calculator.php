<?php
/**
 * Tax calculator utility file
 * Centralized tax calculation to ensure consistency throughout the application
 */

/**
 * Calculate the tax amount for a given subtotal
 * 
 * @param int $subtotal The subtotal in MMK (whole number)
 * @param float $tax_rate Tax rate as a decimal (default: 0.05 = 5%)
 * @return int The tax amount in MMK (whole number)
 */
function calculateTax($subtotal, $tax_rate = 0.05) {
    // Ensure subtotal is an integer (whole number for MMK)
    $subtotal = (int)$subtotal;
    
    // Calculate tax - ensure it's a whole number for MMK
    return round($subtotal * $tax_rate);
}

/**
 * Calculate the total amount including tax and delivery fee
 * 
 * @param int $subtotal The subtotal in MMK (whole number)
 * @param int $delivery_fee The delivery fee in MMK (whole number)
 * @param float $tax_rate Tax rate as a decimal (default: 0.05 = 5%)
 * @return int The total amount in MMK (whole number)
 */
function calculateTotal($subtotal, $delivery_fee, $tax_rate = 0.05) {
    // Calculate tax
    $tax = calculateTax($subtotal, $tax_rate);
    
    // Calculate total amount
    return $subtotal + $tax + $delivery_fee;
}

/**
 * Validate that the provided totals are consistent
 * 
 * @param int $subtotal The subtotal in MMK (whole number)
 * @param int $tax The tax amount in MMK (whole number)
 * @param int $delivery_fee The delivery fee in MMK (whole number)
 * @param int $total_amount The total amount in MMK (whole number)
 * @param float $tax_rate Tax rate as a decimal (default: 0.05 = 5%)
 * @return array ['valid' => bool, 'message' => string, 'corrected_values' => array] 
 */
function validateTotals($subtotal, $tax, $delivery_fee, $total_amount, $tax_rate = 0.05) {
    // Calculate the expected tax
    $expected_tax = calculateTax($subtotal, $tax_rate);
    
    // Calculate the expected total
    $expected_total = $subtotal + $expected_tax + $delivery_fee;
    
    // Initialize response
    $response = [
        'valid' => true,
        'message' => 'Values are consistent',
        'corrected_values' => []
    ];
    
    // Check if tax is inconsistent
    if (abs($tax - $expected_tax) > 1) { // Allow for small rounding differences
        $response['valid'] = false;
        $response['message'] = 'Tax calculation is inconsistent';
        $response['corrected_values']['tax'] = $expected_tax;
    }
    
    // Check if total is inconsistent
    if (abs($total_amount - $expected_total) > 1) { // Allow for small rounding differences
        $response['valid'] = false;
        $response['message'] = 'Total calculation is inconsistent';
        $response['corrected_values']['total_amount'] = $expected_total;
    }
    
    return $response;
} 