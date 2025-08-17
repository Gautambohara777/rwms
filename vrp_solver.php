<?php
// vrp_solver.php
// Content-Type: application/json
header("Content-Type: application/json");

// Input JSON format:
/*
{
  "depot": {"lat": 27.7172, "lng": 85.3240},
  "vehicles": [
     {"id": 5, "capacity": 300},
     {"id": 6, "capacity": 250}
  ],
  "stops": [
     {"id": 101, "lat": 27.69, "lng": 85.28, "demand": 50},
     {"id": 102, "lat": 27.71, "lng": 85.27, "demand": 70},
     ...
  ]
}
*/

function getJsonInput() {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!$data) { http_response_code(400); echo json_encode(["error"=>"Invalid JSON"]); exit; }
  return $data;
}

function haversine($a, $b){
  $R = 6371;
  $dLat = deg2rad($b['lat'] - $a['lat']);
  $dLon = deg2rad($b['lng'] - $a['lng']);
  $lat1 = deg2rad($a['lat']); $lat2 = deg2rad($b['lat']);
  $s = sin($dLat/2)**2 + cos($lat1)*cos($lat2)*sin($dLon/2)**2;
  return $R*2*atan2(sqrt($s), sqrt(1-$s)); // km
}

function osrm_matrix($points){
  // points: [ [lng,lat], ... ] depot first
  $coords = implode(';', array_map(fn($p)=>$p[0].','.$p[1], $points));
  $url = "https://router.project-osrm.org/table/v1/driving/$coords?annotations=distance,duration";
  $ctx = stream_context_create(['http'=>['timeout'=>5]]);
  $json = @file_get_contents($url, false, $ctx);
  if (!$json) return null;
  $data = json_decode($json,true);
  if (!isset($data['distances'])) return null;
  return $data['distances']; // meters
}

$in = getJsonInput();
$depot = $in['depot'];
$vehicles = $in['vehicles'];
$stops = $in['stops'];

// Build matrix (depot + stops)
$nodes = [ ['id'=>0,'lat'=>$depot['lat'],'lng'=>$depot['lng']] ];
foreach ($stops as $s) $nodes[] = $s;

// Try OSRM matrix
$points = array_map(fn($n)=>[$n['lng'],$n['lat']], $nodes);
$distM = osrm_matrix($points);

// Fallback: Haversine matrix (km -> meters)
if (!$distM){
  $n = count($nodes);
  $distM = array_fill(0,$n,array_fill(0,$n,0));
  for($i=0;$i<$n;$i++){
    for($j=0;$j<$n;$j++){
      $distM[$i][$j] = $i==$j ? 0 : haversine($nodes[$i], $nodes[$j])*1000.0;
    }
  }
}

// Clarke–Wright Savings
$nStops = count($stops);
if ($nStops === 0){ echo json_encode(['routes'=>[]]); exit; }

// Initial: every stop is its own route (depot -> i -> depot)
$routes = []; // each: ['stops'=>[idxs], 'load'=>x, 'dist'=>meters]
foreach ($stops as $idx=>$s) {
  $nodeIdx = $idx+1;
  $dist = $distM[0][$nodeIdx] + $distM[$nodeIdx][0];
  $routes[] = ['stops'=>[$nodeIdx], 'load'=>$s['demand']??0, 'dist'=>$dist];
}

// Savings list S_ij = d(0,i)+d(0,j)-d(i,j)
$savings = [];
for($i=1;$i<=count($stops);$i++){
  for($j=$i+1;$j<=count($stops);$j++){
    $s = $distM[0][$i] + $distM[0][$j] - $distM[$i][$j];
    $savings[] = ['i'=>$i,'j'=>$j,'s'=>$s];
  }
}
usort($savings, fn($a,$b)=> $b['s'] <=> $a['s']); // desc

// Helper: find route index containing node k (at end for merge constraints)
function findRouteIdx($routes,$k){
  foreach($routes as $ri=>$r){
    if (in_array($k,$r['stops'])) return $ri;
  }
  return -1;
}

// Merge respecting capacity (greedy)
$capSum = array_sum(array_map(fn($v)=>$v['capacity'],$vehicles));
$maxCap = max(array_map(fn($v)=>$v['capacity'],$vehicles)); // per-vehicle cap (simple)

// Try merges
foreach ($savings as $sv){
  $i=$sv['i']; $j=$sv['j'];
  $ri = findRouteIdx($routes,$i);
  $rj = findRouteIdx($routes,$j);
  if ($ri<0 || $rj<0 || $ri==$rj) continue;

  // Only merge if i is end of ri OR start; likewise for j (classic CW)
  $riStart = $routes[$ri]['stops'][0];
  $riEnd   = end($routes[$ri]['stops']);
  $rjStart = $routes[$rj]['stops'][0];
  $rjEnd   = end($routes[$rj]['stops']);

  $canMerge = false; $mergedStops=null;

  // ri end -> rj start
  if ($riEnd==$i && $rjStart==$j){
    $mergedStops = array_merge($routes[$ri]['stops'], $routes[$rj]['stops']);
    $canMerge=true;
  }
  // rj end -> ri start
  elseif ($rjEnd==$j && $riStart==$i){
    $mergedStops = array_merge($routes[$rj]['stops'], $routes[$ri]['stops']);
    $canMerge=true;
  }

  if (!$canMerge) continue;

  // Capacity check (very simple: one vehicle capacity)
  $newLoad = $routes[$ri]['load'] + $routes[$rj]['load'];
  if ($newLoad > $maxCap) continue;

  // Compute new distance: depot + sequence + depot
  $dist = $distM[0][$mergedStops[0]];
  for($k=0;$k<count($mergedStops)-1;$k++){
    $dist += $distM[$mergedStops[$k]][$mergedStops[$k+1]];
  }
  $dist += $distM[end($mergedStops)][0];

  // Apply merge
  $routes[$ri] = ['stops'=>$mergedStops,'load'=>$newLoad,'dist'=>$dist];
  array_splice($routes, $rj, 1);
}

// Assign routes to vehicles (largest-load first)
usort($routes, fn($a,$b)=> $b['load'] <=> $a['load']);
usort($vehicles, fn($a,$b)=> $b['capacity'] <=> $a['capacity']);

$assigned = [];
foreach ($routes as $r){
  // pick first vehicle that fits
  $vehIdx = null;
  foreach ($vehicles as $vi=>$v){
    if (($assigned[$v['id']]['used'] ?? 0) + $r['load'] <= $v['capacity']){
      $vehIdx = $vi; break;
    }
  }
  if ($vehIdx===null) $vehIdx = 0; // force assign (over-capacity fallback)

  $veh = $vehicles[$vehIdx];
  $assigned[$veh['id']]['routes'][] = $r;
  $assigned[$veh['id']]['used'] = ($assigned[$veh['id']]['used'] ?? 0) + $r['load'];
}

// Build output with human stop IDs (convert node index -> stop.id)
$out = [];
foreach ($assigned as $vehId=>$pack){
  foreach ($pack['routes'] as $r){
    $ids = array_map(function($nodeIdx) use ($stops){
      return $stops[$nodeIdx-1]['id'];
    }, $r['stops']);
    $out[] = [
      'vehicle_id'=>$vehId,
      'stop_ids'=>$ids,
      'total_km'=> round($r['dist']/1000, 2),
      'load'=> $r['load']
    ];
  }
}

echo json_encode(['routes'=>$out], JSON_PRETTY_PRINT);
