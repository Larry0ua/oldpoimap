<?php
error_reporting(E_ALL);
date_default_timezone_set('UTC');
require '../config.php';

$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

if (preg_match("/\/api\/poi\/(\\d+)(\/[nwr])?$/", $request, $result)) {
	// one POI request
	$osm_id = $result[1];
	// skip $2 as not supported by us yet
	$c = new PDO('pgsql:dbname='.DB_NAME.';host=localhost;port=5432', DB_USER, DB_PASS);
	$c->prepare('select * from pois_inspection where osm_id = ?');
	$c->execute(array($osm_id));
	$row = $c->fetch(PDO::FETCH_ASSOC);
	if ($row === FALSE) {
		header("HTTP/1.0 404 Not Found");
	} else {
		echo json_encode($row);
	}
	die("get point with osm_id=$result[1]");
} else if (preg_match("/\/api\/poi\/\?([0-9.,]+)$/", $request, $result)) {
	$bbox = $result[1];
	$coords = explode(',', $bbox); // x1, y1, x2, y2
	if (count($coords) != 4) {
		header("HTTP/1.0 400 Bad request");
		die();
	}
	$x1 = floatval($coords[0]);
	$y1 = floatval($coords[1]);
	$x2 = floatval($coords[2]);
	$y2 = floatval($coords[3]);

	$s = abs(($x1-$x2)*($y1-$y2));
	if ($s > MAX_SQ || $s == 0) {
		header("HTTP/1.0 509 Bad area selected");
	}

	$c = new PDO('pgsql:dbname='.DB_NAME.';host=localhost;port=5432', DB_USER, DB_PASS);
	$c->prepare('select * from pois_inspection where centroid <@ box((?, ?), (?, ?)');
	$c->execute(array($x1, $y1, $x2, $y2));
	$row = $c->fetch(PDO::FETCH_ASSOC);
	if ($row === FALSE) {
		header("HTTP/1.0 404 Not Found");
	} else {
		echo json_encode($row);
	}

	die("find by bbox $coords[0] $coords[1]");
} else {
	die("Unrecognized path $request with method $method");
}

/*$data = $_REQUEST['data'];
$action = $_REQUEST['action'];
$data = json_decode($data, true);

//var_dump($data);
if(!$data) die('No data provided');

$c = new PDO('pgsql:dbname='.DB_NAME.';host=localhost;port=5432', DB_USER, DB_PASS);
*/
/*if ($action=='put' && count($data)>0) {
	$stmt_sel = $c->prepare('select osm_version, check_date from pois_inspection where osm_id = ?');
	foreach ($data as $poi) {
		$stmt_sel->execute(array($poi['id']));
		$ftch = $stmt_sel->fetch(PDO::FETCH_NUM);
		
		if ($ftch === FALSE) { 
		    $pois_to_save[] = $poi;
//		    echo "<br>will add poi";
		} else {
		    if ($ftch[0] != $poi['version']) {
		        $pois_to_update[] = $poi;
//    		    echo "<br>will update poi in database";
		    } 
			
			$date_from_db = strtotime($ftch[1]);
			$date_from_client = strtotime($poi['timestamp']);
		    if ($date_from_db > $date_from_client) {
		        $pois_to_return[(string)$poi['id']]=$ftch[1];
//				echo "<br>will return newer poi ".$poi['id'];
		    }
		}
	}
*/