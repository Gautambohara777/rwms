<?php
// Start a new session or resume the existing one.
session_start();

// Include the database connection file.
require_once 'connect.php';

// Get the user ID from the session provided by your login page.
$userId = $_SESSION['user'] ?? null;
$userName = $_SESSION['user_name'] ?? 'Guest';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reusable Waste Marketplace</title>
    <!-- Updated styling for a modern, clean, and attractive layout -->
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #e6f7ff;
            color: #333;
            line-height: 1.6;
        }

        .filter-container {
            text-align: center;
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .filter-container label {
            font-size: 1.1em;
            margin-right: 10px;
            color: #555;
            font-weight: 600;
        }

        .filter-container select {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 1em;
            outline: none;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .filter-container select:focus {
            border-color: #4a90e2;
        }

        .search-container {
            display: flex;
            gap: 10px;
            width: 100%;
            max-width: 400px;
            align-items: center;
        }

        .search-container input {
            flex-grow: 1;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 1em;
            outline: none;
            transition: border-color 0.3s;
        }

        .search-container input:focus {
            border-color: #4a90e2;
        }

        .search-container select {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 1em;
            outline: none;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .search-container select:focus {
            border-color: #4a90e2;
        }

        .item-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            justify-content: center;
        }

        .item-card {
            background-color: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            position: relative;
            height: 400px;
        }

        .item-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .item-card-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-bottom: 4px solid #4a90e2;
        }

        .item-card-content {
            padding: 25px;
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .item-card h2 {
            margin: 0;
            font-size: 1.8em;
            color: #2980b9;
            font-weight: 600;
        }

        .item-price {
            font-size: 1.4em;
            color: #27ae60;
            font-weight: bold;
            margin-top: 5px;
        }

        .no-items {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            font-size: 1.2em;
            padding: 50px;
        }

        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
            color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            text-transform: uppercase;
        }

        .status-badge.available {
            background-color: #2ecc71;
        }

        .status-badge.reserved {
            background-color: #e74c3c;
        }

        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
            padding-top: 60px;
            animation: fadeIn 0.5s;
        }

        .modal-content {
            background-color: #ffffff;
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: slideIn 0.5s;
        }

        .modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            transition: color 0.2s;
        }

        .modal-close:hover,
        .modal-close:focus {
            color: #333;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-image {
            width: 100%;
            height: auto;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .interest-button {
            display: block;
            width: 100%;
            padding: 15px;
            margin-top: 20px;
            font-size: 1.2em;
            font-weight: bold;
            color: #ffffff;
            background-color: #2ecc71;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background-color 0.3s ease-in-out;
        }

        .interest-button:hover {
            background-color: #27ae60;
        }

        .contact-details {
            display: none;
            background-color: #f1f1f1;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: left;
            border-left: 5px solid #4a90e2;
        }

        .contact-details h3 {
            margin-top: 0;
            color: #4a90e2;
            font-size: 1.2em;
        }

        .contact-details p {
            margin: 5px 0;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <?php if ($userId): ?>
        <div class="filter-container">
            <form id="filterForm" method="get" action="">
                <div class="search-container">
                    <input type="text" name="search_query" id="search-input" placeholder="Search for items..." value="<?php echo htmlspecialchars($_GET['search_query'] ?? ''); ?>">
                    <select name="status" id="status-filter">
                        <option value="">All</option>
                        <option value="Available" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Available') ? 'selected' : ''; ?>>Available</option>
                        <option value="Reserved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Reserved') ? 'selected' : ''; ?>>Reserved</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="item-container">
            <?php
            // Check if the database connection was successful.
            if (isset($con) && $con->ping()) {
                // Get the listing IDs the current user has shown interest in
                $userInterests = [];
                // Use a prepared statement to prevent SQL injection
                $interestsQuery = "SELECT listing_id FROM user_interests WHERE user_id = ?";
                $stmt = $con->prepare($interestsQuery);
                if ($stmt) {
                    $stmt->bind_param("s", $userId);
                    $stmt->execute();
                    $interestsResult = $stmt->get_result();
                    while ($row = $interestsResult->fetch_assoc()) {
                        $userInterests[] = $row['listing_id'];
                    }
                    $stmt->close();
                }

                // Start building the SQL query for the main listings
                $sql = "SELECT listing_id, title, description, quantity, image, status, price FROM reusable_waste_listings";
                $whereClauses = [];

                // Add a WHERE clause for the search query
                if (isset($_GET['search_query']) && $_GET['search_query'] != '') {
                    $searchQuery = $con->real_escape_string($_GET['search_query']);
                    $whereClauses[] = "title LIKE '%$searchQuery%'";
                }

                // By default, only show 'Available' listings unless a specific filter is used
                if (!isset($_GET['status']) || $_GET['status'] === '') {
                    $whereClauses[] = "status = 'Available'";
                } else {
                    $status = $con->real_escape_string($_GET['status']);
                    $whereClauses[] = "status = '$status'";
                }

                // Combine all WHERE clauses with 'AND'
                if (!empty($whereClauses)) {
                    $sql .= " WHERE " . implode(" AND ", $whereClauses);
                }

                // Add the sorting clause
                $sql .= " ORDER BY title ASC";

                // Execute the query
                $result = $con->query($sql);

                // Check if the query execution was successful
                if ($result) {
                    // Check if the query returned any rows
                    if ($result->num_rows > 0) {
                        // Loop through each row of the result set
                        while($row = $result->fetch_assoc()) {
                            // Add a new property to indicate if the user is interested
                            $row['isInterested'] = in_array($row['listing_id'], $userInterests);
                            ?>
                            <div class="item-card" onclick='showDetails(<?php echo json_encode($row); ?>)'>
                                <!-- Display item image, using a placeholder if none is provided -->
                                <img src="<?php echo !empty($row["image"]) ? htmlspecialchars($row["image"]) : 'https://placehold.co/400x250/e3f2fd/1a237e?text=No+Image'; ?>" alt="<?php echo htmlspecialchars($row["title"]); ?>" class="item-card-image">

                                <div class="item-card-content">
                                    <h2><?php echo htmlspecialchars($row["title"]); ?></h2>
                                    <div class="item-price">RS: <?php echo htmlspecialchars($row["price"]); ?></div>
                                    <!-- Display the status of the item -->
                                    <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                        <?php echo htmlspecialchars($row["status"]); ?>
                                    </span>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        // Display a message if no items are found in the database
                        echo "<p class='no-items'>No reusable items found at this time.</p>";
                    }
                } else {
                    // Display a specific error message if the query failed
                    echo "<p class='no-items'>Error executing query: " . $con->error . "</p>";
                }

                // Close the database connection to free up resources
                $con->close();

            } else {
                // Display an error message if the database connection failed
                echo "<p class='no-items'>Failed to connect to the database. Please check your credentials.</p>";
            }
            ?>
        </div>

        <!-- Modal for displaying listing details -->
        <div id="listingModal" class="modal">
            <div class="modal-content">
                <span class="modal-close" onclick="closeModal()">&times;</span>
                <img id="modalImage" class="modal-image" src="" alt="Listing Image">
                <h2 id="modalTitle"></h2>
                <p><strong class="info">Description:</strong> <span id="modalDescription"></span></p>
                <p><strong class="info">Quantity:</strong> <span id="modalQuantity"></span></p>
                <p><strong class="info">Price:</strong> <span id="modalPrice"></span></p>
                <p><strong class="info">Status:</strong> <span id="modalStatus"></span></p>
                <input type="hidden" id="modalListingId">
                <button class="interest-button" onclick="confirmPurchase()">Show Interest</button>
                <p id="confirmationMessage" style="display: none;"></p>
                <div id="contactDetails" class="contact-details">
                    <h3>Contact Recycle Hub</h3>
                    <p><strong>Name:</strong> Recycle Hub</p>
                    <p><strong>Phone:</strong> 9876543210</p>
                    <p><strong>Email:</strong> recyclehub@gmail.com</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <p class='no-items'>Please log in to view listings.</p>
    <?php endif; ?>

    <script>
        const modal = document.getElementById('listingModal');
        const modalImage = document.getElementById('modalImage');
        const modalTitle = document.getElementById('modalTitle');
        const modalDescription = document.getElementById('modalDescription');
        const modalQuantity = document.getElementById('modalQuantity');
        const modalPrice = document.getElementById('modalPrice');
        const modalStatus = document.getElementById('modalStatus');
        const modalListingId = document.getElementById('modalListingId');
        const confirmationMessage = document.getElementById('confirmationMessage');
        const contactDetails = document.getElementById('contactDetails');
        const searchInput = document.getElementById('search-input');
        const statusFilter = document.getElementById('status-filter');
        const filterForm = document.getElementById('filterForm');
        const interestButton = document.querySelector('.interest-button');


        function showDetails(item) {
            modalImage.src = item.image ? item.image : 'https://placehold.co/400x250/e3f2fd/1a237e?text=No+Image';
            modalTitle.textContent = item.title;
            modalDescription.textContent = item.description;
            modalQuantity.textContent = item.quantity;
            modalPrice.textContent = `RS:${item.price}`;
            modalStatus.textContent = item.status;
            modalListingId.value = item.listing_id;
            
            // Check the status of the item or if the user is interested.
            // A listing is considered unavailable to show interest in if it's reserved or the user has already expressed interest.
            if (item.status === 'Reserved' || item.isInterested) {
                // Hide the button and show the message/contact info
                interestButton.style.display = 'none';
                confirmationMessage.textContent = 'You have already shown interest in this item. Contact the seller to confirm your purchase.';
                confirmationMessage.style.color = '#2ecc71';
                confirmationMessage.style.display = 'block';
                contactDetails.style.display = 'block';
            } else {
                // If the item is available and the user hasn't shown interest, show the button
                interestButton.style.display = 'block';
                confirmationMessage.style.display = 'none';
                contactDetails.style.display = 'none';
            }
            
            modal.style.display = 'block';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function confirmPurchase() {
            const listingId = modalListingId.value;
            const userId = "<?php echo $userId; ?>";
            if (!listingId || !userId) {
                console.error("Listing ID or User ID not found.");
                return;
            }

            // Send an AJAX request to the server
            fetch('handle_interest.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ listing_id: listingId, user_id: userId }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    confirmationMessage.textContent = 'Informed to the seller. If you would like to confirm the buy, please contact the seller.';
                    confirmationMessage.style.color = '#2ecc71';
                    confirmationMessage.style.display = 'block';
                    contactDetails.style.display = 'block'; // Show the contact info
                    interestButton.style.display = 'none'; // Hide the "Show Interest" button
                } else {
                    confirmationMessage.textContent = 'Failed to record your interest. Please try again later.';
                    confirmationMessage.style.color = '#e74c3c';
                    confirmationMessage.style.display = 'block';
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                confirmationMessage.textContent = 'An error occurred. Please try again later.';
                confirmationMessage.style.color = '#e74c3c';
                confirmationMessage.style.display = 'block';
            });
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        // Add event listeners for live filtering
        searchInput.addEventListener('input', () => {
            filterForm.submit();
        });

        statusFilter.addEventListener('change', () => {
            filterForm.submit();
        });
    </script>
</body>
</html>
