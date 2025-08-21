<?php
include "connect.php";
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $status = "available";
    $user_id = 1; // admin (or session user id)

    // Handle file upload
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            // Save into DB
            $sql = "INSERT INTO reusable_waste_listings 
                        (title, description, quantity, price, image, status, user_id) 
                    VALUES 
                        ('$title', '$description', $quantity, $price, '$targetFilePath', '$status', $user_id)";

            if (mysqli_query($con, $sql)) {
                $message = "✅ Product listed successfully!";
            } else {
                $message = "❌ Database Error: " . mysqli_error($con);
            }
        } else {
            $message = "❌ File upload failed.";
        }
    } else {
        $message = "❌ No image uploaded or upload error.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Add Product</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { padding: 15px; border: 1px solid #ccc; width: 400px; }
        input, textarea { width: 100%; padding: 8px; margin: 5px 0; }
        button { padding: 10px 15px; background: green; color: #fff; border: none; cursor: pointer; }
        .msg { margin: 10px 0; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Add New Product</h2>

    <?php if ($message): ?>
        <div class="msg"><?= $message ?></div>
    <?php endif; ?>

    <form action="adminlist.php" method="post" enctype="multipart/form-data">
        <label>Title:</label>
        <input type="text" name="title" required>

        <label>Description:</label>
        <textarea name="description" required></textarea>

        <label>Quantity:</label>
        <input type="number" name="quantity" required>

        <label>Price:</label>
        <input type="number" step="0.01" name="price" required>

        <label>Upload Image:</label>
        <input type="file" name="image" accept="image/*" required>

        <button type="submit">Save Product</button>
    </form>
</body>
</html>
