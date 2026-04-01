<?php
require_once '../config/db.php';


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    echo '<p>Unauthorized access.</p>';
    exit;
}

$dealerUserId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dealer Analytics</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
<style>
/* GLOBAL */
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
body { background: #f5f7fa; padding: 30px; color: #333; }
h2 { text-align: center; color: #111; font-size: 2.2rem; margin-bottom: 5px; }
p.subtitle { text-align: center; color: #666; font-size: 1rem; margin-bottom: 40px; }

/* DASHBOARD GRID */
.dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; margin-bottom: 40px; }
.card { position: relative; padding: 25px 20px; border-radius: 16px; color: #fff; overflow: hidden; cursor: pointer; transition: all 0.3s ease; background: linear-gradient(135deg, #007bff, #00c6ff); box-shadow: 0 8px 20px rgba(0,0,0,0.08);}
.card:hover { transform: translateY(-8px); box-shadow: 0 12px 25px rgba(0,0,0,0.12); }
.card .icon { font-size: 2.8rem; margin-bottom: 12px; display: block; }
.card h3 { font-size: 1.8rem; margin-bottom: 6px; }
.card p { font-size: 0.95rem; color: rgba(255,255,255,0.9); }

/* Card Colors */
.card-blue { background: linear-gradient(135deg,#007bff,#00c6ff);}
.card-green { background: linear-gradient(135deg,#28a745,#7be495);}
.card-yellow { background: linear-gradient(135deg,#ffc107,#ffe680); color: #333;}
.card-red { background: linear-gradient(135deg,#dc3545,#ff6b6b);}

/* CHARTS */
.charts-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 30px; margin-bottom: 50px; }
.chart-box { background: #fff; padding: 30px 25px; border-radius: 18px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); transition: transform 0.3s ease; }
.chart-box:hover { transform: translateY(-5px); }
.chart-box h3 { margin-bottom: 25px; font-size: 1.3rem; color: #111; }

/* TABLE */
table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 20px rgba(0,0,0,0.08);}
th, td { padding: 15px 18px; text-align: left; }
th { background:#007bff; color:#fff; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
tbody tr:hover { background: #f0f4ff; transition: background 0.3s ease; }

/* ENGAGEMENT */
.engagement { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px; margin-top: 30px;}
.engagement p { background: #fff; padding: 20px; border-radius: 16px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); font-size: 1.1rem; text-align: center; transition: transform 0.3s ease; }
.engagement p:hover { transform: translateY(-5px); }

/* RESPONSIVE */
@media(max-width:768px){ .charts-container { grid-template-columns: 1fr; } .dashboard-grid { grid-template-columns: 1fr 1fr; } }
@media(max-width:480px){ .dashboard-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<h2>Dealer Analytics</h2>
<p class="subtitle">A sleek, modern overview of your leads, cars, and engagement</p>

<!-- Key Metrics Cards -->
<div class="dashboard-grid">
    <div class="card card-blue">
        <div class="icon">📥</div>
        <h3 id="totalLeads">0 <span id="trendLeads" style="font-size:0.8rem"></span></h3>
        <canvas id="sparkLeads" style="width:100%;height:40px;"></canvas>
        <p>Total Leads</p>
    </div>
    <div class="card card-green">
        <div class="icon">📞</div>
        <h3 id="contactedLeads">0 <span id="trendContacted" style="font-size:0.8rem"></span></h3>
        <canvas id="sparkContacted" style="width:100%;height:40px;"></canvas>
        <p>Leads Contacted</p>
    </div>
    <div class="card card-yellow">
        <div class="icon">🚗</div>
        <h3 id="carsListed">0 <span id="trendListed" style="font-size:0.8rem"></span></h3>
        <canvas id="sparkListed" style="width:100%;height:40px;"></canvas>
        <p>Cars Listed</p>
    </div>
    <div class="card card-red">
        <div class="icon">✅</div>
        <h3 id="carsSold">0 <span id="trendSold" style="font-size:0.8rem"></span></h3>
        <canvas id="sparkSold" style="width:100%;height:40px;"></canvas>
        <p>Cars Sold</p>
    </div>
</div>

<!-- Charts -->
<div class="charts-container">
    <div class="chart-box">
        <h3>Leads Over Time</h3>
        <canvas id="leadsChart" height="250"></canvas>
    </div>
    <div class="chart-box">
        <h3>Cars Listed vs Sold</h3>
        <canvas id="carsChart" height="250"></canvas>
    </div>
</div>

<!-- Top Car Models Table -->
<h3>Top 5 Car Models</h3>
<table>
    <thead>
        <tr><th>Model</th><th>Units Sold</th><th>Average Price</th></tr>
    </thead>
    <tbody id="topModelsBody"></tbody>
</table>

<!-- Engagement Metrics -->
<h3>Engagement Metrics</h3>
<div class="engagement">
    <p><strong>Profile Views:</strong> <span id="profileViews">0</span></p>
    <p><strong>Car Listing Views:</strong> <span id="carViews">0</span></p>
    <p><strong>TikTok Clicks:</strong> <span id="tiktokClicks">0</span></p>
</div>

<script>
// Store chart instances globally to avoid multiple redraws
let charts = {
    sparkLeads: null,
    sparkContacted: null,
    sparkListed: null,
    sparkSold: null,
    leadsChart: null,
    carsChart: null
};

// Counter animation
function animateCounter(el, value) {
    if(value <= 0) { el.textContent = 0; return; }
    let start = 0;
    const duration = 1200;
    const stepTime = Math.max(Math.floor(duration / value), 10);
    const timer = setInterval(() => {
        start += 1;
        el.textContent = start;
        if(start >= value) clearInterval(timer);
    }, stepTime);
}
// Sparkline rendering with gradient (pad with zeros at start for continuous line)
function renderSparkline(id, data, color){
    const ctx = document.getElementById(id).getContext('2d');
    if(charts[id]) charts[id].destroy();

    if(!Array.isArray(data)) data = [];
    if(data.length < 7) data = [...Array(7 - data.length).fill(0), ...data];

    // Create a gradient for sparklines
    const gradient = ctx.createLinearGradient(0, 0, 0, 40);
    gradient.addColorStop(0, color);  // Start with the passed color
    gradient.addColorStop(1, 'rgba(0,0,0,0.1)');  // Fade out at the bottom

    charts[id] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map((_, i) => i + 1),
            datasets: [{
                data: data,
                borderColor: gradient,
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                pointRadius: 0,
                spanGaps: true
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { display: false }, y: { display: false } },
            animation: { x: { duration: 1200 }, y: { duration: 1200 } }
        }
    });
}

// Live trend (persistent)
let previousValues = {};
function liveTrend(id, value){
    const el = document.getElementById(id);
    if(!el) return;
    const prev = previousValues[id] || 0;
    const diff = value - prev;
    previousValues[id] = value;
    if(diff === 0){ el.textContent=''; return; }
    el.textContent = `${diff>0?'▲':'▼'}${Math.abs(diff)}%`;
    el.style.color = diff>0 ? '#00ffb3' : '#ff4d4d';
}

// Cars bar chart rendering with gradient
function renderCarsChart(months, listed, sold){
    const ctxCars = document.getElementById('carsChart').getContext('2d');
    if(charts.carsChart) charts.carsChart.destroy();

    const len = Math.max(months.length, listed.length, sold.length);
    if(months.length < len) months = [...months, ...Array(len - months.length).fill('')];
    if(listed.length < len) listed = [...listed, ...Array(len - listed.length).fill(0)];
    if(sold.length < len) sold = [...sold, ...Array(len - sold.length).fill(0)];

    // Create gradients
    const gradientListed = ctxCars.createLinearGradient(0, 0, 0, 250);
    gradientListed.addColorStop(0, 'rgba(255,193,7,0.8)');  // Top
    gradientListed.addColorStop(1, 'rgba(255,193,7,0.2)');  // Bottom

    const gradientSold = ctxCars.createLinearGradient(0, 0, 0, 250);
    gradientSold.addColorStop(0, 'rgba(220,53,69,0.8)');    // Top
    gradientSold.addColorStop(1, 'rgba(220,53,69,0.2)');    // Bottom

    charts.carsChart = new Chart(ctxCars,{
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                { label: 'Cars Listed', data: listed, backgroundColor: gradientListed, borderRadius: 10 },
                { label: 'Cars Sold', data: sold, backgroundColor: gradientSold, borderRadius: 10 }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            animation: { duration: 1000 },
            scales: { 
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: '#eee' } }
            }
        }
    });
}

// Fetch analytics from server
function fetchAnalytics(){
    fetch('modules/analytics_ajax.php')
    .then(res => res.json())
    .then(data => {
        if(data.status !== 'success') return;

        // Counters
        animateCounter(document.getElementById('totalLeads'), data.totalLeads);
        animateCounter(document.getElementById('contactedLeads'), data.contactedLeads);
        animateCounter(document.getElementById('carsListed'), data.carsListed);
        animateCounter(document.getElementById('carsSold'), data.carsSold);

        // Engagement
        animateCounter(document.getElementById('profileViews'), data.profileViews);
        animateCounter(document.getElementById('carViews'), data.carViews);
        animateCounter(document.getElementById('tiktokClicks'), data.tiktokClicks);

        // Sparklines
        renderSparkline('sparkLeads', data.spark.totalLeads, '#fff');
        renderSparkline('sparkContacted', data.spark.contactedLeads, '#fff');
        renderSparkline('sparkListed', data.spark.carsListed, '#000');
        renderSparkline('sparkSold', data.spark.carsSold, '#fff');

        // Update trends
        liveTrend('trendLeads', data.totalLeads);
        liveTrend('trendContacted', data.contactedLeads);
        liveTrend('trendListed', data.carsListed);
        liveTrend('trendSold', data.carsSold);

        // Leads chart
        const ctxLeads = document.getElementById('leadsChart').getContext('2d');
        if(charts.leadsChart) charts.leadsChart.destroy();
        const gradientLeads = ctxLeads.createLinearGradient(0,0,0,250);
        gradientLeads.addColorStop(0,'rgba(0,123,255,0.4)');
        gradientLeads.addColorStop(1,'rgba(0,123,255,0.05)');
        charts.leadsChart = new Chart(ctxLeads,{
            type: 'line',
            data: {
                labels: data.leadsOverTime.dates,
                datasets: [{
                    label: 'Leads Received',
                    data: data.leadsOverTime.counts,
                    backgroundColor: gradientLeads,
                    borderColor: '#007bff',
                    borderWidth: 2,
                    tension: 0.3,
                    pointBackgroundColor: '#007bff',
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#eee' } }
                }
            }
        });

        // Cars chart
        renderCarsChart(data.carsOverTime.months, data.carsOverTime.listed, data.carsOverTime.sold);

        // Top models table
        const topModelsBody = document.getElementById('topModelsBody');
        topModelsBody.innerHTML = '';
        data.topModels.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${row.model}</td><td>${row.unitsSold}</td><td>${row.avgPrice}</td>`;
            topModelsBody.appendChild(tr);
        });
    })
    .catch(err => console.error('Analytics fetch error:', err));
}

// Initial load
fetchAnalytics();

// Refresh every 60s
setInterval(fetchAnalytics, 60000);
</script>
</body>
</html>