<?php 

require_once('OSTerrain50Reader.php');

// Receive the data from a JSON string request as:
// {locations: [{easting: x, northing: y, elevation: null}, ...], doInfills: bool}
$request = json_decode(file_get_contents("php://input"));

// Set up the data reader with the path to the OS Terrain 50 binary data file
$dataReader = new OSTerrain50Reader("OSTerrain50.bin"); 

// Get the elevation for each location (plus any generated intermediate locations)
$results = $dataReader->getElevations($request->locations, $request->doInfills);

// Return to caller as an array of locations as:
// [{easting: x, northing: y, elevation: z}, ...] with the elevation values populated
header('Content-type: application/json');
echo json_encode($results);