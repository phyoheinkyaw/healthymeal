// Initialize cart count from localStorage
document.addEventListener('DOMContentLoaded', function() {
    updateCartCountFromStorage();
});

function updateCartCountFromStorage() {
    const savedCartCount = localStorage.getItem('cartCount');
    if (savedCartCount) {
        const cartCountElement = document.getElementById('cartCount');
        if (cartCountElement) {
            cartCountElement.textContent = savedCartCount;
        }
    }
}