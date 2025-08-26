<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geolocation Tester</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl max-w-2xl w-full text-center">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Location Tester</h1>
        <p id="status" class="text-lg text-gray-600 mb-6 transition-all duration-300 ease-in-out">
            Checking browser for geolocation support...
        </p>
        <div class="relative w-full h-96 bg-gray-200 rounded-xl overflow-hidden shadow-inner">
            <iframe id="mapFrame" class="w-full h-full border-0" style="display: none;"></iframe>
            <div id="loading" class="absolute inset-0 flex items-center justify-center text-gray-500 font-medium text-lg">
                <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Attempting to find your location...
            </div>
        </div>
    </div>

    <script>
        // Get references to the HTML elements
        const statusEl = document.getElementById('status');
        const mapFrame = document.getElementById('mapFrame');
        const loadingEl = document.getElementById('loading');

        // Function to show the map and hide the loading indicator
        function showMap() {
            loadingEl.style.display = 'none';
            mapFrame.style.display = 'block';
        }

        // Check if the browser supports geolocation
        if (navigator.geolocation) {
            statusEl.textContent = "Please grant permission to view your location on the map.";
            // Request the user's current position
            navigator.geolocation.getCurrentPosition(
                // Success callback: if the location is found
                (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    statusEl.textContent = "Your location has been found successfully!";
                    mapFrame.src = `https://maps.google.com/maps?q=${lat},${lon}&z=15&output=embed`;
                    showMap();
                },
                // Error callback: if there's a problem getting the location
                (error) => {
                    let message = "An unknown error occurred.";
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            message = "Permission to access location was denied. Please allow it in your browser settings.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = "Your location is currently unavailable. Please check your network connection.";
                            break;
                        case error.TIMEOUT:
                            message = "The request to get your location timed out.";
                            break;
                    }
                    statusEl.textContent = `Error: ${message}`;
                    loadingEl.textContent = `Error: ${message}`;
                    // Fallback to a default location (Kathmandu)
                    mapFrame.src = `https://maps.google.com/maps?q=Kathmandu&z=12&output=embed`;
                    showMap();
                }
            );
        } else {
            // Geolocation is not supported by the browser
            statusEl.textContent = "Geolocation is not supported by this browser.";
            loadingEl.textContent = "Geolocation is not supported.";
            // Fallback to a default location (Kathmandu)
            mapFrame.src = `https://maps.google.com/maps?q=Kathmandu&z=12&output=embed`;
            showMap();
        }
    </script>
</body>
</html>
