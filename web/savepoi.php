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

$c = new PDO('pgsql:dbname='.DB_NAME.';host=localhost;port=5432', DB_USER, DB_PASS);
$utc = new DateTimeZone('UTC');

// save from client to db
$pois_to_save = array();
$pois_to_update = array();
$pois_to_return = array();
$added = 0;
$updated = 0;
$error_str = '';
if ($action=='put' && count($data)>0) {
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

	if (count($pois_to_save)>0) {
    	$stmt_put = $c->prepare('insert into pois_inspection (osm_id, osm_type, osm_version, centroid, check_date) values (?, 0, ?, point(?, ?), ?)');
    	foreach ($pois_to_save as $poi) {
    		$success = $stmt_put->execute(array($poi['id'], $poi['version'], $poi['lon'], $poi['lat'], $poi['timestamp']));
    		if ($success) $added++;
			print_r($stmt_put->errorInfo());
//    		echo "<br>add:$success";
    	}
	}
	if (count($pois_to_update)>0) {
    	$stmt_put = $c->prepare('update pois_inspection set osm_version = ?, centroid = point(?, ?), check_date = ? where osm_id = ?');
    	foreach ($pois_to_update as $poi) {
    		$success = $stmt_put->execute(array($poi['id'], $poi['version'], $poi['lon'], $poi['lat'], $poi['timestamp']));
    		if ($success) $updated++;
    		//echo "<br>updated $poi_id(".$stmt_put->affected_rows.':'.$stmt_put->error.':'.$success.')';
    	}
	}
    echo json_encode(array("updated"=>$updated, "added"=>$added, "sent"=>count($data), "newer"=>$pois_to_return));
}
elseif($action=='update' && $data) {
    $stmt_sel = $c->prepare('select check_date from pois_inspection where osm_id = ?');
    $stmt_sel->execute(array($data['id']));
    $ftch=$stmt_sel->fetch();
    if($ftch === FALSE) {
        $error_str = 'Not found yet, put data first';
    } else {
        $stmt_upd = $c->prepare('update pois_inspection set check_date=CURRENT_TIMESTAMP where osm_id = ?');
        $success = $stmt_upd->execute(array($data['id']));
        if ($success) $updated++;
    }
    echo json_encode(array("updated"=>$updated, "message"=>$error_str));
}
//insert into pois_inspection (osm_id, osm_type, osm_version, centroid, check_date) values(1465020990, 0, 1, PointFromText(POINT(25.9362105 48.2907257)), 2011-10-13T11:34:06Z)
//1465020990, 1, POINT(25.9362105 48.2907257), 2011-10-13T11:34:06Z
?>