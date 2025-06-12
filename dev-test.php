<?php

session_start();

class ShoppingCart {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Add item to cart
    public function addToCart($productId, $quantity) {
        $query = "SELECT * FROM products WHERE id = " . $productId;
        $result = mysqli_query($this->db, $query);
        $product = mysqli_fetch_assoc($result);
        
        if (!$product) {
            return false;
        }
        
        if ($product['stock'] < $quantity) {
            return false;
        }
        
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = array();
        }
        
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId] += $quantity;
        } else {
            $_SESSION['cart'][$productId] = $quantity;
        }
        
        return true;
    }
    
    // Calculate total price
    public function getTotal() {
        $total = 0;
        
        if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
            return $total;
        }
        
        foreach ($_SESSION['cart'] as $productId => $quantity) {
            $query = "SELECT price FROM products WHERE id = " . $productId;
            $result = mysqli_query($this->db, $query);
            $product = mysqli_fetch_assoc($result);
            
            $total += $product['price'] * $quantity;
        }
        
        return $total;
    }
    
    // Apply discount code
    public function applyDiscount($code) {
        $query = "SELECT * FROM discount_codes WHERE code = '" . $code . "'";
        $result = mysqli_query($this->db, $query);
        $discount = mysqli_fetch_assoc($result);
        
        if ($discount && $discount['expires'] > date('Y-m-d')) {
            $_SESSION['discount'] = $discount['percentage'];
            return true;
        }
        
        return false;
    }
    
    // Get final total with discount
    public function getFinalTotal() {
        $total = $this->getTotal();
        
        if (isset($_SESSION['discount'])) {
            $discountAmount = $total * ($_SESSION['discount'] / 100);
            $total = $total - $discountAmount;
        }
        
        return round($total, 2);
    }
    
    // Process checkout
    public function checkout($userId, $paymentMethod) {
        $total = $this->getFinalTotal();
        
        if ($total <= 0) {
            return false;
        }
        
        // Create order
        $orderQuery = "INSERT INTO orders (user_id, total, payment_method, status) 
                      VALUES ($userId, $total, '$paymentMethod', 'pending')";
        
        if (!mysqli_query($this->db, $orderQuery)) {
            return false;
        }
        
        $orderId = mysqli_insert_id($this->db);
        
        // Add order items and update stock
        foreach ($_SESSION['cart'] as $productId => $quantity) {
            $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity) 
                         VALUES ($orderId, $productId, $quantity)";
            mysqli_query($this->db, $itemQuery);
            
            $updateStock = "UPDATE products SET stock = stock - $quantity 
                           WHERE id = $productId";
            mysqli_query($this->db, $updateStock);
        }
        
        // Clear cart
        unset($_SESSION['cart']);
        unset($_SESSION['discount']);
        
        return $orderId;
    }
    
    // Get cart contents for display
    public function getCartContents() {
        $contents = array();
        
        if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
            return $contents;
        }
        
        foreach ($_SESSION['cart'] as $productId => $quantity) {
            $query = "SELECT * FROM products WHERE id = " . $productId;
            $result = mysqli_query($this->db, $query);
            $product = mysqli_fetch_assoc($result);
            
            $contents[] = array(
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $product['price'] * $quantity
            );
        }
        
        return $contents;
    }
}

// Usage example
$db = mysqli_connect('localhost', 'user', 'password', 'ecommerce');

if (isset($_POST['action'])) {
    $cart = new ShoppingCart($db);
    
    switch ($_POST['action']) {
        case 'add':
            $result = $cart->addToCart($_POST['product_id'], $_POST['quantity']);
            echo $result ? 'Added to cart' : 'Failed to add';
            break;
            
        case 'checkout':
            $orderId = $cart->checkout($_SESSION['user_id'], $_POST['payment_method']);
            if ($orderId) {
                echo "Order created: " . $orderId;
            } else {
                echo "Checkout failed";
            }
            break;
            
        case 'apply_discount':
            $result = $cart->applyDiscount($_POST['discount_code']);
            echo $result ? 'Discount applied' : 'Invalid discount code';
            break;
    }
}
