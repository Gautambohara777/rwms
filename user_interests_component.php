<?php
// This component is included in admin_dashboard.php
// AJAX handling is done in the main admin_dashboard.php file
?>

<style>
    .user-interests-container {
        font-family: 'Inter', sans-serif;
    }

    .search-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .search-box {
        position: relative;
        max-width: 500px;
        margin: 0 auto;
    }

    .search-input {
        width: 100%;
        padding: 15px 50px 15px 20px;
        border: 2px solid #e2e8f0;
        border-radius: 50px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #f8fafc;
    }

    .search-input:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .search-icon {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: #a0aec0;
        font-size: 1.2rem;
    }

    .main-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        align-items: start;
    }

    .listings-section {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    .section-title {
        color: #2d3748;
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .listing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .listing-card {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        border: 2px solid transparent;
        border-radius: 15px;
        padding: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .listing-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .listing-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        border-color: #667eea;
    }

    .listing-card:hover::before {
        transform: scaleX(1);
    }

    .listing-card.active {
        border-color: #667eea;
        background: linear-gradient(135deg, #e6f3ff 0%, #f0f8ff 100%);
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
    }

    .listing-card.active::before {
        transform: scaleX(1);
    }

    .listing-title {
        font-size: 1.3rem;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 15px;
        line-height: 1.4;
    }

    .listing-status {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-available {
        background: #c6f6d5;
        color: #22543d;
    }

    .status-pending {
        background: #fed7d7;
        color: #742a2a;
    }

    .status-sold {
        background: #e2e8f0;
        color: #4a5568;
    }

    .interests-section {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        min-height: 400px;
    }

    .interests-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 25px;
    }

    .interest-count {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .no-data {
        text-align: center;
        color: #a0aec0;
        padding: 60px 20px;
        font-size: 1.1rem;
    }

    .no-data i {
        font-size: 3rem;
        margin-bottom: 20px;
        display: block;
    }

    .interests-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }

    .interests-table th {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 20px 15px;
        text-align: left;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .interests-table td {
        padding: 20px 15px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .interests-table tr:hover {
        background: #f8fafc;
    }

    .interests-table tr:last-child td {
        border-bottom: none;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .user-details h4 {
        color: #2d3748;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .user-details p {
        color: #718096;
        font-size: 0.85rem;
    }

    .timestamp {
        color: #a0aec0;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
        color: #667eea;
    }

    .loading i {
        animation: spin 1s linear infinite;
        margin-right: 10px;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 15px;
        padding: 25px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 10px;
    }

    .stat-label {
        color: #718096;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.9rem;
    }

    .export-btn {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .export-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .export-btn:active {
        transform: translateY(0);
    }

    @media (max-width: 1024px) {
        .main-content {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .listing-grid {
            grid-template-columns: 1fr;
        }

        .interests-table {
            font-size: 0.9rem;
        }

        .interests-table th,
        .interests-table td {
            padding: 15px 10px;
        }
    }
</style>

<div class="user-interests-container">
    <!-- Search Section -->
    <div class="search-container">
        <div class="search-box">
            <input type="text" id="searchInput" class="search-input" placeholder="Search listings by title or status...">
            <i class="fas fa-search search-icon"></i>
        </div>
    </div>

    <!-- Statistics Section -->
    <div class="stats-grid" id="statsGrid">
        <!-- Stats will be populated by JavaScript -->
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Listings Section -->
        <div class="listings-section">
            <h2 class="section-title">
                <i class="fas fa-list"></i>
                Reusable Waste Listings
            </h2>
            <div class="listing-grid" id="listing-grid">
                <?php
                if (isset($con) && $con->ping()) {
                    $sql = "SELECT listing_id, title, status FROM reusable_waste_listings ORDER BY listing_id ASC";
                    $result = $con->query($sql);

                    if ($result) {
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                $statusClass = 'status-' . strtolower($row['status']);
                                echo "<div class='listing-card' data-listing-id='" . $row['listing_id'] . "' onclick='showInterests(" . $row['listing_id'] . ", \"" . htmlspecialchars($row['title']) . "\")'>";
                                echo "<h3 class='listing-title'>" . htmlspecialchars($row['title']) . "</h3>";
                                echo "<span class='listing-status " . $statusClass . "'>" . htmlspecialchars($row['status']) . "</span>";
                                echo "</div>";
                            }
                        } else {
                            echo "<div class='no-data'><i class='fas fa-inbox'></i><br>No listings found.</div>";
                        }
                    } else {
                        echo "<div class='no-data'><i class='fas fa-exclamation-triangle'></i><br>Error fetching listings: " . $con->error . "</div>";
                    }
                } else {
                    echo "<div class='no-data'><i class='fas fa-database'></i><br>Failed to connect to the database.</div>";
                }
                ?>
            </div>
        </div>

        <!-- Interests Section -->
        <div class="interests-section">
            <div class="interests-header">
                <h2 class="section-title">
                    <i class="fas fa-users"></i>
                    User Interests
                </h2>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span class="interest-count" id="interestCount">0 interests</span>
                    <button onclick="exportInterests()" class="export-btn" id="exportBtn" style="display: none;">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
            </div>
            
            <div id="interest-content">
                <div class="no-data">
                    <i class="fas fa-hand-pointer"></i>
                    <br>Select a listing to view interested users
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let allListings = [];
    let currentListingId = null;

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadStatistics();
        initializeSearch();
        loadAllListings();
    });

    // Load statistics
    function loadStatistics() {
        fetch('?page=user_interests&action=get_stats')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateStats(data.data);
                }
            })
            .catch(error => {
                console.error('Error loading statistics:', error);
            });
    }

    // Update statistics display
    function updateStats(stats) {
        const statsGrid = document.getElementById('statsGrid');
        statsGrid.innerHTML = `
            <div class="stat-card">
                <div class="stat-number">${stats.totalListings}</div>
                <div class="stat-label">Total Listings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${stats.totalInterests}</div>
                <div class="stat-label">Total Interests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${stats.availableListings}</div>
                <div class="stat-label">Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${stats.pendingListings}</div>
                <div class="stat-label">Pending</div>
            </div>
        `;
    }

    // Load all listings for search functionality
    function loadAllListings() {
        const listingCards = document.querySelectorAll('.listing-card');
        allListings = Array.from(listingCards).map(card => ({
            element: card,
            title: card.querySelector('.listing-title').textContent.toLowerCase(),
            status: card.querySelector('.listing-status').textContent.toLowerCase(),
            id: card.dataset.listingId
        }));
    }

    // Initialize search functionality
    function initializeSearch() {
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            filterListings(searchTerm);
        });
    }

    // Filter listings based on search term
    function filterListings(searchTerm) {
        allListings.forEach(listing => {
            const matches = listing.title.includes(searchTerm) || 
                          listing.status.includes(searchTerm);
            listing.element.style.display = matches ? 'block' : 'none';
        });
    }

    // Show interests for a specific listing
    function showInterests(listingId, listingTitle) {
        // Update active state
        document.querySelectorAll('.listing-card').forEach(card => {
            card.classList.remove('active');
        });
        document.querySelector(`[data-listing-id="${listingId}"]`).classList.add('active');

        currentListingId = listingId;
        const interestContent = document.getElementById('interest-content');
        const interestCount = document.getElementById('interestCount');

        // Show loading state
        interestContent.innerHTML = `
            <div class="loading">
                <i class="fas fa-spinner"></i>
                Loading interests...
            </div>
        `;

        // Fetch interests data
        fetch(`?page=user_interests&action=get_interests&listing_id=${listingId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data); // Debug log
                if (data.status === 'success') {
                    const interests = data.data;
                    interestCount.textContent = `${interests.length} interest${interests.length !== 1 ? 's' : ''}`;
                    
                    if (interests.length > 0) {
                        displayInterests(interests, listingTitle);
                        document.getElementById('exportBtn').style.display = 'flex';
                    } else {
                        interestContent.innerHTML = `
                            <div class="no-data">
                                <i class="fas fa-user-slash"></i>
                                <br>No users have shown interest in this item yet
                            </div>
                        `;
                        document.getElementById('exportBtn').style.display = 'none';
                    }
                } else {
                    console.error('API Error:', data.message); // Debug log
                    interestContent.innerHTML = `
                        <div class="no-data">
                            <i class="fas fa-exclamation-triangle"></i>
                            <br>Error: ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error); // Debug log
                interestContent.innerHTML = `
                    <div class="no-data">
                        <i class="fas fa-exclamation-triangle"></i>
                        <br>Error loading data: ${error.message}
                    </div>
                `;
            });
    }

    // Display interests in a table
    function displayInterests(interests, listingTitle) {
        const interestContent = document.getElementById('interest-content');
        
        let tableHTML = `
            <div style="margin-bottom: 20px;">
                <h3 style="color: #2d3748; margin-bottom: 10px;">Users interested in: ${listingTitle}</h3>
            </div>
            <table class="interests-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Interest Date</th>
                    </tr>
                </thead>
                <tbody>
        `;

        interests.forEach(interest => {
            const initials = interest.name.split(' ').map(n => n[0]).join('').toUpperCase();
            const formattedDate = new Date(interest.timestamp).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            tableHTML += `
                <tr>
                    <td>
                        <div class="user-info">
                            <div class="user-avatar">${initials}</div>
                            <div class="user-details">
                                <h4>${interest.name}</h4>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="user-details">
                            <p><i class="fas fa-envelope"></i> ${interest.email}</p>
                            <p><i class="fas fa-phone"></i> ${interest.phone || 'Not provided'}</p>
                        </div>
                    </td>
                    <td>
                        <span class="timestamp">${formattedDate}</span>
                    </td>
                </tr>
            `;
        });

        tableHTML += `
                </tbody>
            </table>
        `;

        interestContent.innerHTML = tableHTML;
    }

    // Export interests to CSV
    function exportInterests() {
        if (!currentListingId) {
            alert('Please select a listing first');
            return;
        }

        fetch(`?page=user_interests&action=get_interests&listing_id=${currentListingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.data.length > 0) {
                    const csv = convertToCSV(data.data);
                    downloadCSV(csv, `interests_listing_${currentListingId}.csv`);
                } else {
                    alert('No data to export');
                }
            })
            .catch(error => {
                console.error('Error exporting data:', error);
                alert('Error exporting data');
            });
    }

    // Convert data to CSV format
    function convertToCSV(data) {
        const headers = ['Name', 'Email', 'Phone', 'Interest Date'];
        const csvContent = [
            headers.join(','),
            ...data.map(row => [
                `"${row.name}"`,
                `"${row.email}"`,
                `"${row.phone || ''}"`,
                `"${row.timestamp}"`
            ].join(','))
        ].join('\n');
        return csvContent;
    }

    // Download CSV file
    function downloadCSV(csv, filename) {
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }
</script>
