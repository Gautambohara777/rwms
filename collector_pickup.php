<?php
// start_pickup.php
session_start();
include "connect.php";

// Auth: collector only
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'collector') {
    header("Location: login.php");
    exit();
}

$collector_id = (int)$_SESSION['user'];

// Pull only in-progress pickups assigned to this collector
$sql = "SELECT pr.*, u.name AS customer_name
        FROM pickup_requests pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.assigned_collector_id = ?
          AND pr.status = 'in progress'";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "i", $collector_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$pickups = [];
while ($row = mysqli_fetch_assoc($result)) {
    $pickups[] = [
        'id'            => (int)$row['id'],
        'customer_name' => $row['customer_name'],
        'waste_type'    => $row['waste_type'],
        'weight'        => (float)$row['weight'],
        'address'       => $row['address'],
        'lat'           => (float)$row['latitude'],
        'lng'           => (float)$row['longitude'],
        'status'        => strtolower(trim($row['status'] ?? 'in progress'))
    ];
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Live Pickup Route</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <style>
    :root { --green:#2e7d32; --light:#f6faf6; --shadow:0 2px 10px rgba(0,0,0,.08); }
    *{box-sizing:border-box}
    body{font-family:Arial, sans-serif;margin:0;padding:0;background:var(--light);display:flex;flex-direction:column;min-height:100vh;}
    main{flex:1;padding:20px;}
    h2{margin:0 0 10px}
    #map{height:520px;border-radius:12px;box-shadow:var(--shadow)}
    .wrap{
      display:grid;
      grid-template-columns:2fr 1fr;
      gap:16px;margin-top:16px; align-items:start;
    }
    .card{background:#fff;border-radius:12px;padding:16px;box-shadow:0 1px 6px rgba(0,0,0,.06)}
    .leg{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #eee}
    .leg:last-child{border-bottom:none}
    .btn{background:var(--green);color:#fff;padding:8px 12px;border-radius:8px;border:0;cursor:pointer;font-weight:600}
    .pill{display:inline-block;font-size:12px;padding:2px 8px;border-radius:999px;background:#e8f5e9;color:var(--green);margin-left:6px}
    .status{font-weight:bold}
    @media(max-width:900px){.wrap{grid-template-columns:1fr}}
    footer{background:#2e7d32;color:#fff;text-align:center;padding:10px;margin-top:auto;}
  </style>
</head>
<body>
  <?php include 'include/header.php'; ?>
  <main>
    <h2>Live Pickup Route <span class="pill">In-Progress</span></h2>
    <div class="wrap">
      <div id="map" class="card"></div>
      <div class="card">
        <h3 style="margin-top:0">Pickups • Distance • ETA</h3>
        <div id="list"></div>
      </div>
    </div>
  </main>
 

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// Data from PHP
const pickups = <?php echo json_encode($pickups, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;

const map = L.map('map').setView([27.7, 85.3], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ attribution:'© OpenStreetMap contributors' }).addTo(map);

let collectorMarker, routeLine, stopMarkers=[];

// Haversine distance (km)
function haversine(lat1, lon1, lat2, lon2) {
  const R = 6371; // km
  const toRad = deg => deg * Math.PI / 180;
  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);
  const a = Math.sin(dLat/2) ** 2 +
            Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
            Math.sin(dLon/2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// Greedy nearest neighbor VRP
function optimizeRoute(start, stops) {
  const remaining = [...stops];
  const route = [];
  let current = start;
  while (remaining.length) {
    let nearestIdx = 0, nearestDist = Infinity;
    remaining.forEach((s,i)=>{
      const d = haversine(current.lat, current.lng, s.lat, s.lng);
      if(d < nearestDist){ nearestDist=d; nearestIdx=i; }
    });
    current = remaining.splice(nearestIdx,1)[0];
    route.push(current);
  }
  return route;
}

function renderList(route){
  const box = document.getElementById('list');
  if(!route.length){ box.innerHTML = '<em>No pickups in progress</em>'; return; }
  box.innerHTML = route.map((p,i)=>`
    <div class="leg">
      <div>
        <strong>Stop ${i+1}</strong><br>
        ${p.customer_name} • ${p.waste_type} (${p.weight} kg)<br>
        ${p.address}<br>
        <span class="status" style="color:${p.status==='collected'?'green':'#c62828'}">${p.status}</span>
      </div>
      <div><button class="btn" onclick="markCollected(${p.id})">Mark Collected</button></div>
    </div>`).join('');
}

async function plotRoute(start, route){
  stopMarkers.forEach(m=>map.removeLayer(m));
  if(routeLine) map.removeLayer(routeLine);

  collectorMarker = L.marker([start.lat,start.lng],{draggable:false})
    .addTo(map).bindPopup("Collector");

  route.forEach(p=>{
    const mk = L.circleMarker([p.lat,p.lng],{radius:8,color:'blue',fillColor:'blue',fillOpacity:1})
      .addTo(map).bindPopup(`<b>${p.customer_name}</b><br>${p.address}`);
    stopMarkers.push(mk);
  });

  // Request route from OSRM
  const coords = [[start.lng,start.lat], ...route.map(p=>[p.lng,p.lat])]
                  .map(c=>c.join(',')).join(';');
  const url = `https://router.project-osrm.org/route/v1/driving/${coords}?overview=full&geometries=geojson`;

  try {
    const res = await fetch(url);
    const data = await res.json();
    if(data.routes && data.routes.length){
      const geo = data.routes[0].geometry;
      routeLine = L.geoJSON(geo,{style:{color:'green',weight:4}}).addTo(map);
      map.fitBounds(routeLine.getBounds(),{padding:[20,20]});
    }
  } catch(err){ console.error("Routing failed", err); }
}

function initRoute(position){
  const start = {lat: position.coords.latitude, lng: position.coords.longitude};
  if(!pickups.length){ renderList([]); return; }
  const optimized = optimizeRoute(start, pickups);
  renderList(optimized);
  plotRoute(start, optimized);
}

// Fallback if no GPS
function handleError(err){
  alert("Could not get location. Showing pickups only.");
  renderList(pickups);
  drawMap();
}

navigator.geolocation.getCurrentPosition(initRoute, handleError, {enableHighAccuracy:true});

// Update status
async function markCollected(id){
  try{
    await fetch('update_pickup_status.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({id:id,status:'collected'})
    });
    const p = pickups.find(x=>x.id===id);
    if(p) p.status='collected';
    initRoute({coords:{latitude:collectorMarker.getLatLng().lat,longitude:collectorMarker.getLatLng().lng}});
  }catch(e){
    alert('Failed to update status.');
  }
}
</script>

</body> 

</html>
<?php include 'include/footer.php'; ?>
