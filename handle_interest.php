<?php

// Include the database connection file.
require_once 'connect.php';

// Set the content type to JSON to ensure the response is correctly interpreted.
header('Content-Type: application/json');

// Get the raw POST data from the request body.
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if the required data was received.
if (isset($data['listing_id']) && isset($data['user_id'])) {
    $listingId = $data['listing_id'];
    $userId = $data['user_id'];

    // Check if the database connection was successful.
    if (isset($con) && $con->ping()) {
        // Prepare an SQL statement to prevent SQL injection.
        $stmt = $con->prepare("INSERT INTO user_interests (listing_id, user_id) VALUES (?, ?)");
        
        // Bind the parameters and execute the statement.
        $stmt->bind_param("is", $listingId, $userId);
        
        if ($stmt->execute()) {
            // Return a success message if the data was inserted successfully.
            echo json_encode(['success' => true, 'message' => 'User interest has been recorded.']);
        } else {
            // Return an error message if the query failed.
            echo json_encode(['success' => false, 'message' => 'Failed to record user interest: ' . $con->error]);
        }

        // Close the statement and the database connection.
        $stmt->close();
        $con->close();
    } else {
        // Return an error if the database connection failed.
        echo json_encode(['success' => false, 'message' => 'Failed to connect to the database. Please check your credentials.']);
    }

} else {
    // If required data was not provided, return an error message.
    echo json_encode(['success' => false, 'message' => 'Invalid request. Listing ID or User ID is missing.']);
}

?>
