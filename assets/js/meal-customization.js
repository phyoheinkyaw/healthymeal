/**
 * Meal Kit Customization JavaScript
 * This file contains functions for customizing meal kits
 */

// Function to increment quantity
function incrementQuantity() {
    const quantityInput = document.getElementById("meal_quantity");
    if (quantityInput) {
        const currentValue = parseInt(quantityInput.value) || 1;
        if (currentValue < 10) {
            quantityInput.value = currentValue + 1;
            updateNutritionalValues();  // Update calculations when quantity changes
        }
    }
}

// Function to decrement quantity
function decrementQuantity() {
    const quantityInput = document.getElementById("meal_quantity");
    if (quantityInput) {
        const currentValue = parseInt(quantityInput.value) || 1;
        if (currentValue > 1) {
            quantityInput.value = currentValue - 1;
            updateNutritionalValues();  // Update calculations when quantity changes
        }
    }
}

// Initialize event listeners when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
    // Add event listeners to ingredient quantity inputs that exist in the DOM
    initializeQuantityListeners();
    
    // Initialize cart count from localStorage on every page load
    updateCartCountFromStorage();
});

// Function to update cart count from localStorage
function updateCartCountFromStorage() {
    const savedCartCount = localStorage.getItem('cartCount') || '0';
    const cartCountElement = document.getElementById('cartCount');
    if (cartCountElement) {
        cartCountElement.textContent = savedCartCount;
        // Always show the badge
        cartCountElement.style.display = 'inline-block';
    }
}

// Function to update cart count in navbar
function updateCartCount(count) {
    // If count is provided, use it; otherwise, use the value from localStorage
    const cartCount = count || localStorage.getItem('cartCount') || 0;
    const cartCountElement = document.getElementById('cartCount');
    if (cartCountElement) {
        cartCountElement.textContent = cartCount;
        // Always show the cart count badge, even if it's zero
        cartCountElement.style.display = 'inline-block';
    }
    localStorage.setItem('cartCount', cartCount);
    
    // Optionally fetch the updated count from the database too
    // This ensures other parts of the site also get updated
    fetch('api/cart/get_cart_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && cartCountElement) {
                cartCountElement.textContent = data.count;
                // Always show the badge
                cartCountElement.style.display = 'inline-block';
            }
        })
        .catch(error => console.log('Error updating cart count from database:', error));
}

// Function to initialize quantity listeners (can be called after AJAX loads content)
function initializeQuantityListeners() {
    // Check if we're on a page with meal customization elements
    if (!document.querySelector(".ingredient-quantity") && !document.getElementById("meal_quantity")) {
        // We're not on a page with meal customization elements
        console.log("No meal customization elements found, skipping initialization");
        return;
    }

    // Add listeners to all ingredient quantity inputs
    document.querySelectorAll(".ingredient-quantity").forEach(input => {
        input.addEventListener("change", function() {
            updateNutritionalValues();
        });
        
        // Also listen for input events to update in real-time
        input.addEventListener("input", function() {
            updateNutritionalValues();
        });
    });

    // Add listener to meal quantity input
    const mealQuantityInput = document.getElementById("meal_quantity");
    if (mealQuantityInput) {
        mealQuantityInput.addEventListener("change", function() {
            updateNutritionalValues();
        });
        
        // Also listen for input events to update in real-time
        mealQuantityInput.addEventListener("input", function() {
            updateNutritionalValues();
        });
    }
    
    // Call once to initialize values
    updateNutritionalValues();
}

// Function to update nutritional values
function updateNutritionalValues() {
    // Check if basePrice element exists before accessing its properties
    const basePriceElement = document.getElementById("basePrice");
    if (!basePriceElement) {
        console.warn("Base price element not found");
        return; // Exit function early if element doesn't exist
    }
    
    // Get the base price (preparation price) from the DOM
    const basePriceText = basePriceElement.textContent || "0";
    const basePrice = parseFloat(basePriceText.replace(/,/g, "")) || 0;
    
    // Check if meal_quantity element exists
    const mealQuantityInput = document.getElementById("meal_quantity");
    if (!mealQuantityInput) {
        console.warn("Meal quantity input not found");
        return;
    }
    const mealQuantity = parseInt(mealQuantityInput.value) || 1;

    console.log("Initial basePrice:", basePrice);
    console.log("Initial mealQuantity:", mealQuantity);

    let totalCalories = 0;
    let totalProtein = 0;
    let totalCarbs = 0;
    let totalFat = 0;
    let ingredientsPrice = 0;

    // Iterate through each ingredient row to get nutrition and price
    document.querySelectorAll(".ingredient-row").forEach(row => {
        const quantityInput = row.querySelector(".ingredient-quantity");
        if (!quantityInput) return;
        
        const quantity = parseFloat(quantityInput.value) || 0;
        const caloriesPer100g = parseFloat(row.dataset.calories) || 0;
        const proteinPer100g = parseFloat(row.dataset.protein) || 0;
        const carbsPer100g = parseFloat(row.dataset.carbs) || 0;
        const fatPer100g = parseFloat(row.dataset.fat) || 0;
        const pricePer100g = parseFloat(row.dataset.price) || 0;

        // Calculate nutrient and price values for current ingredient quantity
        const calories = (caloriesPer100g * quantity) / 100;
        const protein = (proteinPer100g * quantity) / 100;
        const carbs = (carbsPer100g * quantity) / 100;
        const fat = (fatPer100g * quantity) / 100;
        const price = (pricePer100g * quantity) / 100;

        // Sum values to running totals
        totalCalories += calories;
        totalProtein += protein;
        totalCarbs += carbs;
        totalFat += fat;
        ingredientsPrice += price;

        // Update individual row cells with formatted values
        const caloriesCell = row.querySelector(".calories-cell");
        const proteinCell = row.querySelector(".protein-cell");
        const carbsCell = row.querySelector(".carbs-cell");
        const fatCell = row.querySelector(".fat-cell");
        const priceCell = row.querySelector(".price-cell");
        
        if (caloriesCell) caloriesCell.textContent = Math.round(calories) + " cal";
        if (proteinCell) proteinCell.textContent = protein.toFixed(1) + "g";
        if (carbsCell) carbsCell.textContent = carbs.toFixed(1) + "g";
        if (fatCell) fatCell.textContent = fat.toFixed(1) + "g";
        if (priceCell) priceCell.textContent = Math.round(price).toLocaleString() + " MMK";
    });

    console.log("Total ingredients price:", ingredientsPrice);

    // Calculate single meal price (preparation price + all ingredients)
    const singleMealPrice = basePrice + ingredientsPrice;
    console.log("Calculated singleMealPrice:", singleMealPrice);
    
    // Calculate total price based on meal quantity
    const totalPrice = singleMealPrice * mealQuantity;
    console.log("Calculated totalPrice:", totalPrice);

    // Update summary elements - check if they exist first
    const totalCalElement = document.getElementById("totalCalories");
    const totalProteinElement = document.getElementById("totalProtein");
    const totalCarbsElement = document.getElementById("totalCarbs");
    const totalFatElement = document.getElementById("totalFat");
    const ingredientsPriceElement = document.getElementById("ingredientsPrice");
    const singleMealPriceElement = document.getElementById("singleMealPrice");
    const totalPriceElement = document.getElementById("totalPrice");
    
    if (totalCalElement) totalCalElement.textContent = Math.round(totalCalories);
    if (totalProteinElement) totalProteinElement.textContent = totalProtein.toFixed(1);
    if (totalCarbsElement) totalCarbsElement.textContent = totalCarbs.toFixed(1);
    if (totalFatElement) totalFatElement.textContent = totalFat.toFixed(1);
    if (ingredientsPriceElement) ingredientsPriceElement.textContent = Math.round(ingredientsPrice).toLocaleString();
    if (singleMealPriceElement) singleMealPriceElement.textContent = Math.round(singleMealPrice).toLocaleString();
    if (totalPriceElement) totalPriceElement.textContent = Math.round(totalPrice).toLocaleString();
    
    // Also update cells directly using class selectors
    const singleMealPriceCell = document.querySelector(".single-meal-price-cell");
    if (singleMealPriceCell) {
        singleMealPriceCell.innerHTML = '<strong><span id="singleMealPrice">' + Math.round(singleMealPrice).toLocaleString() + '</span> MMK</strong>';
        console.log("Updated single meal price cell");
    }
    
    const totalPriceCell = document.querySelector(".total-price-cell");
    if (totalPriceCell) {
        totalPriceCell.innerHTML = '<strong><span id="totalPrice">' + Math.round(totalPrice).toLocaleString() + '</span> MMK</strong>';
        console.log("Updated total price cell");
    }
}

// Standard function for adding to cart - to be used by both meal-kits.php and meal-details.php
function standardAddToCart(mealKitId, quantity) {
    const form = document.getElementById('customizeForm');
    if (!form) {
        console.error('Customization form not found');
        return;
    }

    // Convert form data to JSON structure
    const formData = new FormData(form);
    const jsonData = {
        meal_kit_id: mealKitId,
        ingredients: {},
        quantity: parseInt(quantity) || 1
    };

    // Process ingredient quantities
    formData.forEach((value, key) => {
        if (key.startsWith('ingredients[')) {
            const ingredientId = key.match(/\[(\d+)\]/)[1];
            jsonData.ingredients[ingredientId] = parseFloat(value) || 0;
        }
    });

    // Get calculated totals
    const totalCaloriesElement = document.getElementById('totalCalories');
    jsonData.total_calories = totalCaloriesElement ? parseFloat(totalCaloriesElement.textContent) || 0 : 0;
    
    const basePriceElement = document.getElementById('basePrice');
    const basePriceText = basePriceElement ? (basePriceElement.textContent || "0") : "0";
    jsonData.preparation_price = parseFloat(basePriceText.replace(/,/g, "")) || 0;
    
    const ingredientsPriceElement = document.getElementById('ingredientsPrice');
    const ingredientsPriceText = ingredientsPriceElement ? (ingredientsPriceElement.textContent || "0") : "0";
    jsonData.ingredients_price = parseFloat(ingredientsPriceText.replace(/,/g, "")) || 0;
    
    const singleMealPriceElement = document.getElementById('singleMealPrice');
    const singleMealPriceText = singleMealPriceElement ? (singleMealPriceElement.textContent || "0") : "0";
    jsonData.singleMealPrice = parseFloat(singleMealPriceText.replace(/,/g, "")) || 0;
    
    const totalPriceElement = document.getElementById('totalPrice');
    const totalPriceText = totalPriceElement ? (totalPriceElement.textContent || "0") : "0";
    jsonData.total_price = parseFloat(totalPriceText.replace(/,/g, "")) || 0;

    console.log("Sending data to cart:", jsonData);

    fetch('api/cart/add_item.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(jsonData)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update cart count
                updateCartCount(data.total_items);

                // Show success message
                const toast = new bootstrap.Toast(document.getElementById('successToast'));
                toast.show();

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('customizeModal'));
                modal.hide();
            } else {
                // Show error message
                document.getElementById('errorToastMessage').textContent = data.message || 'Error adding item to cart';
                const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                toast.show();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('errorToastMessage').textContent = 'Error adding item to cart. Please try again.';
            const toast = new bootstrap.Toast(document.getElementById('errorToast'));
            toast.show();
        });
}