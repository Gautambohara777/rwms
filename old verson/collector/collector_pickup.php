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

// Fetch in-progress pickups (with phone + rate)
$sql = "SELECT pr.*, u.name AS customer_name, u.phone 
        FROM pickup_requests pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.assigned_collector_id = ?
          AND pr.status = 'In Progress'";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "i", $collector_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$pickups = [];
while ($row = mysqli_fetch_assoc($result)) {
    $pickups[] = [
        'id'            => (int)$row['id'],
        'customer_name' => $row['customer_name'],
        'phone'         => $row['phone'],
        'waste_type'    => $row['waste_type'],
        'weight'        => (float)$row['weight'],
        'rate'          => (float)$row['rate'],
        'address'       => $row['address'],
        'lat'           => (float)$row['latitude'],
        'lng'           => (float)$row['longitude']
    ];
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Live Optimized Pickup Route</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
  <style>
    :root { --green:#2e7d32; --light:#f6faf6; --shadow:0 2px 10px rgba(0,0,0,.08); }
    body{margin:0;font-family:Arial, sans-serif;background:var(--light);}
    main{padding:20px;}
    #map{height:520px;border-radius:12px;box-shadow:var(--shadow)}
    .wrap{display:grid;grid-template-columns:3fr 1fr;gap:16px;margin-top:50px;}
    .card{background:#fff;border-radius:12px;padding:16px;box-shadow:0 1px 6px rgba(0,0,0,.06)}
    .leg{border-bottom:1px solid #eee;padding:10px 0;}
    .btn{background:var(--green);color:#fff;padding:6px 10px;margin:4px;border:0;border-radius:6px;cursor:pointer;}
    .steps{margin-bottom:12px;padding:10px;background:#f9f9f9;border-radius:8px;}
  </style>
</head>
<body>
  <?php include 'include/header.php'; ?>
  <main>
    <a href="collector_dashboard.php" class="btn secondary">Back to Dashboard</a>
    <h2>Live Optimized Pickup Route (Greedy Nearest)</h2>
    <div class="wrap">
      <div id="map" class="card"></div>
      <div class="card">
        <h3>Direction to Current Pickup</h3>
        <div id="steps"></div>
        <h3>Pickup List</h3>
        <div id="list"></div>
      </div>
    </div>
  </main>
  <?php include 'include/footer.php'; ?>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>
  <script>
  const pickups = <?php echo json_encode($pickups); ?>;

  const map = L.map('map').setView([27.7, 85.3], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
    attribution:'© OSM contributors'
  }).addTo(map);

  let collectorMarker, routingControl, otherMarkers=[];

  function haversine(lat1, lon1, lat2, lon2){
    const R=6371, dLat=(lat2-lat1)*Math.PI/180, dLon=(lon2-lon1)*Math.PI/180;
    const a=Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
    return R*2*Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }

  function greedyOrder(start, stops){
    let order=[], current={...start}, remaining=[...stops];
    while(remaining.length){
      let nearest=remaining.reduce((a,b)=>haversine(current.lat,current.lng,a.lat,a.lng)<haversine(current.lat,current.lng,b.lat,b.lng)?a:b);
      order.push(nearest);
      current=nearest;
      remaining=remaining.filter(p=>p.id!==nearest.id);
    }
    return order;
  }

  function renderRoute(start, ordered){
    if(routingControl) map.removeControl(routingControl);
    otherMarkers.forEach(m=>map.removeLayer(m));
    otherMarkers=[];

    if(!ordered.length) {
      document.getElementById('steps').innerHTML = "<p>No pending pickups.</p>";
      return;
    }

    const currentPickup = ordered[0];
    const waypoints = [L.latLng(start.lat,start.lng), L.latLng(currentPickup.lat,currentPickup.lng)];

    routingControl = L.Routing.control({
      waypoints: waypoints,
      lineOptions: {styles: [{color: 'blue', weight: 5}]},
      createMarker: function(i, wp, nWps) {
        if(i===0){
          return L.marker(wp.latLng, {
            icon: L.icon({iconUrl:"https://cdn-icons-png.flaticon.com/512/64/64113.png",iconSize:[25,25]})
          }).bindPopup("You (Collector)");
        } else {
          return L.marker(wp.latLng, {
            icon: L.icon({iconUrl:"https://cdn-icons-png.flaticon.com/512/190/190411.png",iconSize:[25,25]})
          }).bindPopup("Next Pickup: "+currentPickup.customer_name);
        }
      },
      addWaypoints: false,
      routeWhileDragging: false,
      show: false
    }).addTo(map);

    // step-by-step directions
    routingControl.on('routesfound', function(e){
      let html = '<div class="steps"><strong>To: '+currentPickup.customer_name+'</strong><br>';
      e.routes[0].instructions.forEach(step=>{
        html += "• " + step.text + "<br>";
      });
      html += '</div>';
      document.getElementById('steps').innerHTML = html;
    });

    // Add markers for other pickups
    ordered.slice(1).forEach(p=>{
      let m = L.marker([p.lat,p.lng],{
        icon: L.icon({iconUrl:"https://cdn-icons-png.flaticon.com/512/64/64572.png",iconSize:[22,22],iconAnchor:[11,11]})
      }).bindPopup("Other Pickup: "+p.customer_name);
      m.addTo(map);
      otherMarkers.push(m);
    });

    // Pickup list
    let html='';
    ordered.forEach((p,i)=>{
      let dist = haversine(start.lat,start.lng,p.lat,p.lng).toFixed(2);
      html+=`<div class="leg">
        <strong>${i+1}. ${p.customer_name} (${p.phone})</strong><br>
        ${p.waste_type} • ${p.weight}kg • Rate: ${p.rate} • ${dist} km<br>
        <button class="btn" onclick="markCollected(${p.id}, '${p.waste_type}', ${p.weight}, ${p.rate})">Mark Collected</button>
        <button class="btn" onclick="updatePickup(${p.id})">Update Pickup</button>
      </div>`;
    });
    document.getElementById('list').innerHTML=html;
}


  navigator.geolocation.watchPosition(pos=>{
    let start={lat:pos.coords.latitude,lng:pos.coords.longitude};

    if(!collectorMarker){
      collectorMarker=L.marker([start.lat,start.lng],{
        icon:L.icon({iconUrl:"https://cdn-icons-png.flaticon.com/512/64/64113.png",iconSize:[25,25]})
      }).addTo(map).bindPopup("You (Collector)").openPopup();
    }else{
      collectorMarker.setLatLng([start.lat,start.lng]);
    }

    if(pickups.length){
      let ordered=greedyOrder(start,pickups);
      renderRoute(start,ordered);
    }
  },()=>alert("Location access denied. Enable GPS."),{enableHighAccuracy:true});

  async function markCollected(id, waste_type, weight, rate){
    let total = weight * rate;
    try{
      const res=await fetch('collectorupdate_pickupstatus.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
          id, status:'Collected',
          collected_items:waste_type,
          final_weight:weight,
          total_cost:total
        })
      });
      if(res.ok) location.reload();
    }catch(e){ alert('Failed to update.'); }
  }

  function updatePickup(id){
    window.location.href = "collectorupdate_pickup.php?id="+id;
  }
  </script>
</body>
</html>
