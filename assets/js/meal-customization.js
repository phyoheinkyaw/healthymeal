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
            updateNutritionalValues();
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
            updateNutritionalValues();
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
    const savedCartCount = localStorage.getItem('cartCount');
    if (savedCartCount) {
        const cartCountElement = document.getElementById('cartCount');
        if (cartCountElement) {
            cartCountElement.textContent = savedCartCount;
        }
    }
}

// Function to update cart count in navbar
function updateCartCount(count) {
    // If count is provided, use it; otherwise, use the value from localStorage
    const cartCount = count || localStorage.getItem('cartCount') || 0;
    const cartCountElement = document.getElementById('cartCount');
    if (cartCountElement) {
        cartCountElement.textContent = cartCount;
    }
    localStorage.setItem('cartCount', cartCount);
}

// Function to initialize quantity listeners (can be called after AJAX loads content)
function initializeQuantityListeners() {
    document.querySelectorAll(".ingredient-quantity").forEach(input => {
        input.addEventListener("change", function () {
            updateNutritionalValues();
        });
    });

    const mealQuantityInput = document.getElementById("meal_quantity");
    if (mealQuantityInput) {
        mealQuantityInput.addEventListener("change", function () {
            updateNutritionalValues();
        });
    }
}

// Function to update nutritional values
function updateNutritionalValues() {
    const basePrice = parseFloat(document.getElementById("basePrice").textContent) || 0;
    const mealQuantity = parseInt(document.getElementById("meal_quantity").value) || 1;

    let totalCalories = 0;
    let totalProtein = 0;
    let totalCarbs = 0;
    let totalFat = 0;
    let ingredientsPrice = 0;

    document.querySelectorAll(".ingredient-row").forEach(row => {
        const quantity = parseFloat(row.querySelector(".ingredient-quantity").value) || 0;
        const caloriesPer100g = parseFloat(row.dataset.calories) || 0;
        const proteinPer100g = parseFloat(row.dataset.protein) || 0;
        const carbsPer100g = parseFloat(row.dataset.carbs) || 0;
        const fatPer100g = parseFloat(row.dataset.fat) || 0;
        const pricePer100g = parseFloat(row.dataset.price) || 0;

        const calories = (caloriesPer100g * quantity) / 100;
        const protein = (proteinPer100g * quantity) / 100;
        const carbs = (carbsPer100g * quantity) / 100;
        const fat = (fatPer100g * quantity) / 100;
        const price = (pricePer100g * quantity) / 100;

        totalCalories += calories;
        totalProtein += protein;
        totalCarbs += carbs;
        totalFat += fat;
        ingredientsPrice += price;

        row.querySelector(".calories-cell").textContent = Math.round(calories) + " cal";
        row.querySelector(".protein-cell").textContent = protein.toFixed(1) + "g";
        row.querySelector(".carbs-cell").textContent = carbs.toFixed(1) + "g";
        row.querySelector(".fat-cell").textContent = fat.toFixed(1) + "g";
        row.querySelector(".price-cell").textContent = "$" + price.toFixed(2);
    });

    // Calculate single meal price
    const singleMealPrice = parseFloat(basePrice) + parseFloat(ingredientsPrice);
    
    // Calculate total price based on meal quantity
    const totalPrice = singleMealPrice * mealQuantity;

    document.getElementById("totalCalories").textContent = Math.round(totalCalories);
    document.getElementById("totalProtein").textContent = totalProtein.toFixed(1);
    document.getElementById("totalCarbs").textContent = totalCarbs.toFixed(1);
    document.getElementById("totalFat").textContent = totalFat.toFixed(1);
    document.getElementById("ingredientsPrice").textContent = ingredientsPrice.toFixed(2);
    document.getElementById("singleMealPrice").textContent = singleMealPrice.toFixed(2);
    document.getElementById("totalPrice").textContent = totalPrice.toFixed(2);
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
        customization_notes: formData.get('customization_notes') || '',
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
    jsonData.total_calories = parseFloat(document.getElementById('totalCalories').textContent);
    jsonData.preparation_price = parseFloat(document.getElementById('basePrice').textContent);
    jsonData.ingredients_price = parseFloat(document.getElementById('ingredientsPrice').textContent);
    jsonData.total_price = parseFloat(document.getElementById('singleMealPrice').textContent);

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