<?php
include "connect.php";
$message = "";

// Handle Add New Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $status = "available";
    $user_id = 1; // admin or session user id

    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            $sql = "INSERT INTO reusable_waste_listings 
                        (title, description, quantity, price, image, status, user_id) 
                    VALUES 
                        ('$title', '$description', $quantity, $price, '$targetFilePath', '$status', $user_id)";
            mysqli_query($con, $sql);
            $message = "✅ Product listed successfully!";
        } else $message = "❌ File upload failed.";
    } else $message = "❌ No image uploaded.";
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $res = mysqli_query($con, "SELECT image FROM reusable_waste_listings WHERE listing_id=$id");
    if ($row = mysqli_fetch_assoc($res)) {
        if (file_exists($row['image'])) unlink($row['image']);
    }
    mysqli_query($con, "DELETE FROM reusable_waste_listings WHERE listing_id=$id");
    $message = "🗑️ Product deleted.";
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];

    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $targetDir = "uploads/";
        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            mysqli_query($con, "UPDATE reusable_waste_listings 
                                SET title='$title', description='$description', quantity=$quantity, price=$price, image='$targetFilePath' 
                                WHERE listing_id=$id");
        }
    } else {
        mysqli_query($con, "UPDATE reusable_waste_listings 
                            SET title='$title', description='$description', quantity=$quantity, price=$price 
                            WHERE listing_id=$id");
    }
    $message = "✏️ Product updated.";
}

// Fetch all products
$result = mysqli_query($con, "SELECT * FROM reusable_waste_listings ORDER BY listing_id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Manage Waste Products</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .container { display: flex; gap: 30px; }
        .form-box { flex: 1; }
        .list-box { flex: 1.5; }
        form { padding: 15px; border: 1px solid #ccc; margin-bottom: 20px; }
        input, textarea { width: 100%; padding: 8px; margin: 5px 0; }
        button { padding: 8px 12px; margin-top: 5px; cursor: pointer; }
        .msg { margin: 10px 0; font-weight: bold; color: green; }
        .item { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; cursor: pointer; }
        .item img { width: 60px; height: auto; vertical-align: middle; margin-right: 10px; }
        .item-header { display: flex; align-items: center; }
        .item-details { display: none; margin-top: 10px; padding: 10px; border-top: 1px solid #ddd; }
    </style>
    <script>
        function toggleDetails(id) {
            var el = document.getElementById('details-'+id);
            el.style.display = (el.style.display === 'block') ? 'none' : 'block';
        }
    </script>
</head>
<body>
    <h2>Admin Panel - Manage Waste Products</h2>
    <?php if ($message): ?>
        <div class="msg"><?= $message ?></div>
    <?php endif; ?>

    <div class="container">
        <!-- Left: Add New Product -->
        <div class="form-box">
            <h3>Add New Product</h3>
            <form action="adminlist.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="add" value="1">
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
        </div>

        <!-- Right: List of Products -->
        <div class="list-box">
            <h3>Uploaded Products</h3>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <div class="item" onclick="toggleDetails(<?= $row['listing_id'] ?>)">
                    <div class="item-header">
                        <img src="<?= $row['image'] ?>" alt="">
                        <strong><?= htmlspecialchars($row['title']) ?></strong> 
                        (<?= $row['quantity'] ?> pcs) - $<?= $row['price'] ?>
                    </div>
                    <div class="item-details" id="details-<?= $row['listing_id'] ?>">
                        <p><?= htmlspecialchars($row['description']) ?></p>
                        <!-- Edit Form -->
                        <form action="adminlist.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="update" value="1">
                            <input type="hidden" name="id" value="<?= $row['listing_id'] ?>">
                            <label>Title:</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($row['title']) ?>" required>
                            <label>Description:</label>
                            <textarea name="description" required><?= htmlspecialchars($row['description']) ?></textarea>
                            <label>Quantity:</label>
                            <input type="number" name="quantity" value="<?= $row['quantity'] ?>" required>
                            <label>Price:</label>
                            <input type="number" step="0.01" name="price" value="<?= $row['price'] ?>" required>
                            <label>Change Image:</label>
                            <input type="file" name="image" accept="image/*">
                            <button type="submit">Update</button>
                        </form>
                        <!-- Delete -->
                        <a href="adminlist.php?delete=<?= $row['listing_id'] ?>" 
                           onclick="return confirm('Delete this product?');">❌ Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
