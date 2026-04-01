<?php
require_once '../config/db.php';


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    header('Location: dealerlogin.php');
    exit;
}

$dealerId = $_SESSION['user_id'];

// Latest Listings
$latestListings = $pdo->prepare("SELECT id, title, price, status FROM dealer_listings WHERE dealer_id = ? ORDER BY created_at DESC LIMIT 5");
$latestListings->execute([$dealerId]);
$listings = $latestListings->fetchAll(PDO::FETCH_ASSOC);

// Latest Leads
$latestLeads = $pdo->prepare("SELECT name, email, phone, created_at FROM leads WHERE dealer_id = ? ORDER BY created_at DESC LIMIT 5");
$latestLeads->execute([$dealerId]);
$leads = $latestLeads->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
.stats {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
}
.stat-card {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 8px;
    flex: 1;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.recent-section {
    display: flex;
    gap: 16px;
    margin-top: 20px;
}
.recent-card {
    flex: 1;
    background: white;
    padding: 12px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
</style>

<div class="stats">
    <div class="stat-card">
        <h3 id="totalListings">0</h3>
        <p>Total Listings</p>
    </div>
    <div class="stat-card">
        <h3 id="totalSold">0</h3>
        <p>Cars Sold</p>
    </div>
    <div class="stat-card">
        <h3 id="totalLeads">0</h3>
        <p>New Leads</p>
    </div>
</div>

<!-- Chart -->
<canvas id="dealerAnalytics" height="100"></canvas>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
let chart;

function fetchDealerData() {
    fetch('modules/get_dealer_data.php')
        .then(response => response.json())
        .then(data => {
            // Update stats
            document.getElementById('totalListings').innerText = data.totalListings;
            document.getElementById('totalSold').innerText = data.totalSold;
            document.getElementById('totalLeads').innerText = data.totalLeads;

            const labels = data.months;
            const listings = data.listingsFilled;
            const leads = data.leadsFilled;
            const sales = data.salesFilled;

            if (chart) {
                chart.data.labels = labels;
                chart.data.datasets[0].data = listings;
                chart.data.datasets[1].data = leads;
                chart.data.datasets[2].data = sales;
                chart.update();
            } else {
                const ctx = document.getElementById('dealerAnalytics').getContext('2d');
                chart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Listings', data: listings, backgroundColor: 'rgba(43, 138, 237, 0.6)', borderColor: '#2b8aed', borderWidth: 1, yAxisID: 'y' },
                            { label: 'Leads', data: leads, backgroundColor: 'rgba(243, 156, 18, 0.6)', borderColor: '#f39c12', borderWidth: 1, yAxisID: 'y' },
                            { label: 'Sales', data: sales, type: 'line', borderColor: '#27ae60', backgroundColor: '#27ae60', fill: false, tension: 0.3, yAxisID: 'y1' }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'top' } },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Listings / Leads' } },
                            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Sales' } }
                        }
                    }
                });
            }
        })
        .catch(err => console.error('Error fetching dealer data:', err));
}

// Initial load
fetchDealerData();
// Refresh every 5 seconds
setInterval(fetchDealerData, 5000);
</script>

<div class="recent-section">
    <div class="recent-card">
        <h4>Latest Listings</h4>
        <ul>
            <?php foreach ($listings as $car): ?>
                <li><?= htmlspecialchars($car['title']) ?> — $<?= number_format($car['price']) ?> (<?= htmlspecialchars($car['status']) ?>)</li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="recent-card">
        <h4>Latest Leads</h4>
        <ul>
            <?php foreach ($leads as $lead): ?>
                <li><?= htmlspecialchars($lead['name']) ?> — <?= htmlspecialchars($lead['email']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<div class="recent-activity">
    <div class="recent-activity-header">
        <h4>Recent Activity</h4>
        <span id="newActivityBadge" class="new-activity-badge">New Activity!</span>
    </div>
    <div id="activityFeed" class="fade-container">
        <p>Loading recent activity...</p>
    </div>
</div>

<style>
.recent-activity {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.recent-activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.new-activity-badge {
    display: none;
    background: #27ae60;
    color: white;
    padding: 4px 10px;
    font-size: 12px;
    border-radius: 50px;
    animation: badgePulse 0.8s infinite alternate;
}

@keyframes badgePulse {
    0% { transform: scale(1); }
    100% { transform: scale(1.1); }
}

.activity-group {
    margin-bottom: 20px;
}

.activity-group h5 {
    font-size: 14px;
    font-weight: bold;
    color: #555;
    margin-bottom: 8px;
}

.activity-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.activity-list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 8px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
    opacity: 0;
    transform: translateY(8px);
    animation: fadeInUp 0.4s ease forwards;
}

.activity-list li:hover {
    background: #f9f9f9;
}

.activity-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: bold;
    margin-right: 10px;
}

.activity-icon.listing { background: #3498db; }
.activity-icon.sale { background: #27ae60; }
.activity-icon.lead { background: #e67e22; }

.activity-desc {
    flex: 1;
}

.activity-time {
    font-size: 12px;
    color: #999;
    white-space: nowrap;
}

.fade-container {
    opacity: 0;
    transition: opacity 0.4s ease;
}

.fade-container.visible {
    opacity: 1;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulseHighlight {
    0% { background-color: #fff3cd; }
    50% { background-color: #fff8e1; }
    100% { background-color: transparent; }
}

.new-activity {
    animation: pulseHighlight 1.5s ease-out;
}
.highlight-focus {
    animation: focusHighlight 1.5s ease-out;
}

@keyframes focusHighlight {
    0% { background-color: #d1ffd6; }
    50% { background-color: #e5ffe8; }
    100% { background-color: transparent; }
}
</style>

<!-- No need for a separate <audio> tag now -->

<script>
let lastActivityIds = [];
let soundEnabled = false;

// Preload the notification sound
const activitySound = new Audio('assets/notification.m4a');
activitySound.preload = 'auto';

// Enable sound after user interacts (click, scroll, keypress)
document.addEventListener('click', () => { soundEnabled = true; }, { once: true });

// Function to play notification sound
function playNotificationSound() {
    if (soundEnabled) {
        activitySound.currentTime = 0;
        activitySound.play().catch(err => console.log("Sound play blocked:", err));
    }
}

// Group activities by date
function groupActivitiesByDate(data) {
    const groups = { today: [], yesterday: [], earlier: [] };
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    data.forEach(item => {
        const activityDate = new Date(item.created_at);
        if (activityDate.toDateString() === today.toDateString()) {
            groups.today.push(item);
        } else if (activityDate.toDateString() === yesterday.toDateString()) {
            groups.yesterday.push(item);
        } else {
            groups.earlier.push(item);
        }
    });

    return groups;
}

// Show the "New Activity!" badge
function showNewActivityBadge() {
    const badge = document.getElementById('newActivityBadge');
    badge.style.display = 'inline-block';

    badge.onclick = () => {
        const firstNew = document.querySelector('.new-activity');
        if (firstNew) {
            firstNew.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstNew.classList.add('highlight-focus');
            setTimeout(() => {
                firstNew.classList.remove('highlight-focus');
            }, 2000);
        }
        badge.style.display = 'none';
    };

    setTimeout(() => {
        badge.style.display = 'none';
    }, 3000);
}

// Fetch and render recent activity
function fetchRecentActivity() {
    fetch('modules/get_recent_activity.php')
        .then(response => response.json())
        .then(data => {
            const feed = document.getElementById('activityFeed');
            feed.classList.remove('visible');

            setTimeout(() => {
                feed.innerHTML = '';

                if (!data.length) {
                    feed.innerHTML = '<p>No recent activity found.</p>';
                    feed.classList.add('visible');
                    return;
                }

                const grouped = groupActivitiesByDate(data);
                let hasNewActivity = false;
                let trulyNewActivity = false;

                function renderGroup(title, activities) {
                    if (!activities.length) return;
                    const groupDiv = document.createElement('div');
                    groupDiv.className = 'activity-group';

                    const heading = document.createElement('h5');
                    heading.textContent = title;
                    groupDiv.appendChild(heading);

                    const ul = document.createElement('ul');
                    ul.className = 'activity-list';

                    activities.forEach((item, index) => {
                        const li = document.createElement('li');
                        li.style.animationDelay = `${index * 0.05}s`;

                        if (!lastActivityIds.includes(item.id)) {
                            li.classList.add('new-activity');
                            hasNewActivity = true;
                            trulyNewActivity = true;
                        }

                        const icon = document.createElement('div');
                        icon.className = `activity-icon ${item.type}`;
                        icon.innerHTML = item.type === 'listing' ? 'C' :
                                         item.type === 'sale' ? 'S' :
                                         item.type === 'lead' ? 'L' : 'ℹ️';

                        const desc = document.createElement('div');
                        desc.className = 'activity-desc';
                        desc.textContent = item.description;

                        const time = document.createElement('span');
                        time.className = 'activity-time';
                        time.textContent = new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                        li.appendChild(icon);
                        li.appendChild(desc);
                        li.appendChild(time);

                        ul.appendChild(li);
                    });

                    groupDiv.appendChild(ul);
                    feed.appendChild(groupDiv);
                }

                renderGroup('Today', grouped.today);
                renderGroup('Yesterday', grouped.yesterday);
                renderGroup('Earlier', grouped.earlier);

                // Update lastActivityIds
                lastActivityIds = data.map(item => item.id);

                // Show badge and play sound only for truly new activity
                if (hasNewActivity) {
                    showNewActivityBadge();
                    if (trulyNewActivity) {
                        playNotificationSound();
                    }
                }

                feed.classList.add('visible');
            }, 200);
        })
        .catch(err => {
            console.error('Error fetching recent activity:', err);
        });
}

// Initial fetch + repeat every 5 seconds
fetchRecentActivity();
setInterval(fetchRecentActivity, 5000);
</script>