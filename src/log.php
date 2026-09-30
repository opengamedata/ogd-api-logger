<?php
header("Access-Control-Allow-Headers: Origin, Authorization, X-Requested-With, Content-Type, Accept");
header("Access-Control-Allow-Origin: *");

# 3rd-party imports
require 'vendor/autoload.php';
use Google\Cloud\BigQuery\BigQueryClient;
# Local imports
require 'config.php';
require 'bigquery.php';
require 'mysql.php';
require 'query.php';
require 'monitor.php';

$LOGGER_GAMES = array("BACTERIA",   "BALLOON",  "CRYSTAL",    "CYCLE_CARBON", "CYCLE_NITROGEN", "CYCLE_WATER",
                     "EARTHQUAKE", "JOWILDER", "LAKELAND",   "MAGNET",       "WAVES",          "WIND");
# 1. Make the db connection before we go to the trouble of looking at the data.
switch ($db_type) {
   case "bigquery":
      // $conn = new BigQueryClient([ 'projectId' => $db ]);
      error_log("Dummy connect to BQ\n");
      break;
   case "mysql":
      // $conn = mysqli_connect($servername, $username, $password, $db);
      // if (!$conn) {
      //    die("FAIL: Could not connect to the database.\nError message: " . mysqli_connect_error());
      // }
      error_log("Dummy connect to MySQL\n");
      break;
   default:
      die("FAIL: API software was misconfigured, invalid db_type setting!");
      break;
}

# 2. Figure out what the input schema looks like, defaulting to v0.1 OGD schema.
$schema_version = $_REQUEST["schema_version"] ?? "N/A";
switch ($schema_version) {
   case "1.0-alpha":
      $request_schema = $OGD_SCHEMA_10;
      $app_id = strtoupper($_REQUEST["game_id"]) ?? "NO GAME ID";
   default:
      $request_schema = $OGD_SCHEMA_01;
      $app_id = strtoupper($_REQUEST["app_id"]) ?? "NO GAME ID";
      if (in_array($app_id, $LOGGER_GAMES)) {
         $request_schema = $LOGGER_SCHEMA;
      }
      break;
}



# 3. Parse the query data from raw input data.
/**
 * An array of event data bodies, containing event_name, event_data, and similar columns.
* @var data
*/
$data = json_decode(base64_decode($_POST["data"]));
if (!is_array($data)) {
   $d = $data;
   $data = array();
   array_push($data, $d);
}

# 4. Generate and send query.
if (count($data) > 0) {
   $query = new EventQuery($request_schema, $app_id, $data);

   switch ($db_type) {
      case "bigquery":
         $arr = $query->AsBigQuery();
         // $result = BigQueryUtils::Insert($conn, $app_id, $arr);
         $result = "Dummy insert of ".count($arr)." events into BQ.";
         error_log("Sending bigquery response: ".$result);
         break;
      case "mysql":
         $query_string = $query->AsMySQL($db_type, $app_id, $conn);
         // $result = MySQLUtils::Insert($conn, $app_id, $query_string);
         $result = "Dummy run of \n".$query_string."\n in MySQL.";
         error_log("Sending mysql response: ".$result);
         break;
      default:
         die("FAIL: API software was misconfigured, invalid db_type setting!");
         break;
   }
} else {
   die("FAIL: Could not log event(s), no valid data received!");
}

# 5. If successful, forward data to monitor and return.
// sendToMonitor($_REQUEST, $data[0]);
die("SUCCESS: " . $result);

die("Logger endpoint reached, version=".$loggerversion);
?>