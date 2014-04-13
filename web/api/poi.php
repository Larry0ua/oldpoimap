<?php
error_reporting(E_ALL);
date_default_timezone_set('UTC');
require '../config.php';

$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
/*
get: /api/poi/123 - returns poi [{id:123, updated:'date'}] or 404 if not set
get: /api/poi/?1,2,3,4 - returns [{id:123, updated:'date'},]
post: /api/poi/ - add 123 as updated now, post params: id:123, lat:1, lng: 1, returns same object as methods above
put: - no need to update in db
delete: /api/poi/123 - remove from database as osm has a newer version. server-side check?
*/

if ($method == 'GET' && preg_match("/\/api\/poi\/(\\d+)(\/[nwr])?$/", $request, $result)) {
  // one POI request
  $osm_id = $result[1];
  // skip $2 as not supported by us yet
  $c = db_connect();
  $stmt = $c->prepare('select * from pois_inspection where osm_id = ?');
  $stmt->execute(array($osm_id));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row === FALSE) {
    header("HTTP/1.0 404 Not Found");
    die();
  } else {
    echo json_encode(array(array('id'=>$row['osm_id'], 'updated'=>$row['check_date'])));
  }

//////////////////////////////////////////////////////////////////////////////////

} else if ($method == 'GET' && preg_match("/\/api\/poi\/\?([0-9.,-]+)$/", $request, $result)) {
  $bbox = $result[1];
  $coords = explode(',', $bbox); // x1, y1, x2, y2
  if (count($coords) != 4) {
    header("HTTP/1.0 400 Bad request");
    die();
  }
  $y1 = floatval($coords[0]);
  $x1 = floatval($coords[1]);
  $y2 = floatval($coords[2]);
  $x2 = floatval($coords[3]);

  $s = abs(($x1-$x2)*($y1-$y2));
  if ($s > MAX_SQ || $s == 0) {
    header("HTTP/1.0 409 Bad area selected");
    die();
  }

  $c = db_connect();
  $stmt = $c->prepare('select * from pois_inspection where lng between symmetric ? and ? and lat between symmetric ? and ?');
  $stmt->execute(array($x1, $x2, $y1, $y2));
  $result = array();
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $result[] = array('id'=>$row['osm_id'], 'updated'=>$row['check_date']);
  }
  echo json_encode($result);

//////////////////////////////////////////////////////////////////////////////////

} elseif ($method == 'POST' && preg_match('/\/api\/poi\/$/', $request)) {
  $object['id'] = $_POST['id'];
  $object['lat'] = $_POST['lat'];
  $object['lng'] = $_POST['lng'];

  $osm_id = $object['id'];
  if (!is_numeric($osm_id) || $osm_id <= 0) {
    header("HTTP/1.0 409 Bad Request");
    die();
  }
  $c = db_connect();
  $stmt = $c->prepare('select count(*) from pois_inspection where osm_id = ?');
  $stmt->execute(array($osm_id));
  $row = $stmt->fetch(PDO::FETCH_NUM);
  if ($row[0] == 1) {
    $stmt = $c->prepare('update pois_inspection set check_date=CURRENT_TIMESTAMP where osm_id = ?');
    $stmt->execute(array($osm_id));
  } else {
    $stmt = $c->prepare('insert into pois_inspection (osm_id, check_date, lat, lng) values (?, CURRENT_TIMESTAMP, ?, ?)');
    $stmt->execute(array($osm_id, $object['lat'], $object['lng']));
  }
  $stmt = $c->prepare('select * from pois_inspection where osm_id = ?');
  $stmt->execute(array($osm_id));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  echo json_encode(array(array('id'=>$row['osm_id'], 'updated'=>$row['check_date'])));

//////////////////////////////////////////////////////////////////////////////////

} elseif ($method == 'DELETE' && preg_match("/\/api\/poi\/(\\d+)(\/[nwr])?$/", $request, $result)) {
  $c = db_connect();
  $stmt = $c->prepare('delete from pois_inspection where osm_id = ?');
  $stmt->execute(array($result[1]));
} else {
  header("HTTP/1.0 409 Bad Request");
  die("Unrecognized path $request with method $method");
}
