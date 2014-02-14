<?php
error_reporting(E_ALL);
require 'config.php';

$data_text = '{"action":"put", "pois":[
{"id":"1465020996", "version":"1", "lat":"48.2907257", "lon":"25.9362105", "timestamp":"2011-10-13T11:34:06Z"},
{"id":"1465020997", "version":"1", "lat":"48.2907257", "lon":"25.9362105", "timestamp":"2011-10-13T11:34:06Z"},
{"id":"1465020998", "version":"1", "lat":"48.2907257", "lon":"25.9362105", "timestamp":"2011-10-13T11:34:06Z"},
{"id":"1465020981", "version":"6", "lat":"48.2907257", "lon":"25.9362105", "timestamp":"2011-10-13T11:34:06Z"},
{"id":"1465021000", "version":"7", "lat":"48.2907257", "lon":"25.9362105", "timestamp":"2011-10-13T11:34:06Z"}
]}';
$data = $_REQUEST['data'];
$action = $_REQUEST['action'];
$data = json_decode($data, true);

//var_dump($data);
if(!$data) die('No data provided');

$c = new mysqli('localhost', DB_USER, DB_PASS, DB_NAME);
if($c->connect_errno) { die("Db connect failed");}

// save from client to db
$pois_to_save = array();
$pois_to_update = array();
$pois_to_return = array();
$added = 0;
$updated = 0;
$error_str = '';
if ($action=='put' && count($data)>0) {
	$stmt_sel = $c->prepare('select osm_version, check_date from pois_inspection where osm_id = ?');
	$stmt_sel->bind_param("i", $poi_id);
	$stmt_sel->bind_result($result_version, $result_check_date);
//	echo $c->error;
	foreach ($data as $poi) {
		$poi_id = $poi['id'];
		$stmt_sel->execute();
		$ftch = $stmt_sel->fetch();
		//var_dump($ftch);
		if ($ftch === NULL) { 
		    $pois_to_save[] = $poi;
		    //echo "<br>will add poi $poi_id";
		} elseif ($ftch === TRUE) {
		    if ($result_version != $poi['version']) {
		        $pois_to_update[] = $poi;
    		    //echo "<br>will update poi $poi_id";
		    } 
		    if ($result_check_date > $poi['timestamp']) {
		        $pois_to_return[$poi['id']]=$result_check_date;
		    }
		}
	}
	$stmt_sel->close();
	if (count($pois_to_save)>0) {
    	$stmt_put = $c->prepare('insert into pois_inspection (osm_id, osm_type, osm_version, centroid, check_date) values (?, 0, ?, GeomFromText(?, 4326), ?)');
    	$stmt_put->bind_param("iiss", $poi_id, $poi_version, $point_coords, $poi_timestamp);
    	foreach ($pois_to_save as $poi) {
    		$poi_id = $poi['id'];
    		$poi_version = $poi['version'];
            $point_coords = 'POINT('.floatval($poi['lon']).' '.floatval($poi['lat']).')';
            $poi_timestamp = $poi['timestamp'];
    		$success = $stmt_put->execute();
    		if ($success) $added++;
    		//echo "<br>added $poi_id(".$stmt_put->affected_rows.':'.$stmt_put->error.':'.$success.')';
    	}
    	$stmt_put->close();
	}
	if (count($pois_to_update)>0) {
    	$stmt_put = $c->prepare('update pois_inspection set osm_version = ?, centroid = GeomFromText(?, 4326), check_date = ? where osm_id = ?');
    	$stmt_put->bind_param("issi", $poi_version, $point_coords, $poi_timestamp, $poi_id);
    	foreach ($pois_to_update as $poi) {
    		$poi_id = $poi['id'];
    		$poi_version = $poi['version'];
            $point_coords = 'POINT('.floatval($poi['lon']).' '.floatval($poi['lat']).')';
            $poi_timestamp = $poi['timestamp'];
    		$success = $stmt_put->execute();
    		if ($success) $updated++;
    		//echo "<br>updated $poi_id(".$stmt_put->affected_rows.':'.$stmt_put->error.':'.$success.')';
    	}
    	$stmt_put->close();
	}
    echo json_encode(array("updated"=>$updated, "added"=>$added, "sent"=>count($data), "newer"=>$pois_to_return));
}
elseif($action=='update' && $data) {
    $stmt_sel = $c->prepare('select check_date from pois_inspection where osm_id = ?');
    $stmt_sel->bind_param("i", $poi_id);
    $stmt_sel->bind_result($result_check_date);
    $poi_id=$data['id'];
    $stmt_sel->execute();
    $ftch=$stmt_sel->fetch();
    $stmt_sel->close();
    if($ftch === NULL) {
        $error_str = 'Not found yet, put data first';
    } elseif ($ftch === TRUE) {
        $stmt_upd = $c->prepare('update pois_inspection set check_date=now() where osm_id = ?');
        $stmt_upd->bind_param("i", $poi_id);
        $success = $stmt_upd->execute();
        if ($success) $updated++;
        $stmt_upd->close();
    }
    echo json_encode(array("updated"=>$updated, "message"=>$error_str));
}
//insert into pois_inspection (osm_id, osm_type, osm_version, centroid, check_date) values(1465020990, 0, 1, PointFromText(POINT(25.9362105 48.2907257)), 2011-10-13T11:34:06Z)
//1465020990, 1, POINT(25.9362105 48.2907257), 2011-10-13T11:34:06Z
?>