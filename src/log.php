<?php
header("Access-Control-Allow-Headers: Origin, Authorization, X-Requested-With, Content-Type, Accept");
header("Access-Control-Allow-Origin: *");

# 3rd-party imports
require_once 'vendor/autoload.php';
# Local imports
require_once 'config.php';
require_once 'bigquery.php';
require_once 'mysql.php';
require_once 'query.php';
require_once 'monitor.php';

use Logger\EventQuery;

const LOGGER_SCHEMA = "LOGGER";
const OGD_SCHEMA_01 = "OPENGAMEDATA_V0";
const OGD_SCHEMA_10 = "OPENGAMEDATA_V1";

const LOGGER_GAMES = array("BACTERIA",   "BALLOON",  "CRYSTAL",    "CYCLE_CARBON", "CYCLE_NITROGEN", "CYCLE_WATER",
                     "EARTHQUAKE", "JOWILDER", "LAKELAND",   "MAGNET",       "WAVES",          "WIND");

# 1. CONNECT to DB before we go to the trouble of looking at the data.
switch ($db_type) {
   case "bigquery":
      $conn = BigQueryUtils::Connect();
      break;
   case "mysql":
      $conn = MySQLUtils::Connect($servername, null, null, $db);
      break;
   default:
      die("FAIL: API software was misconfigured, invalid db_type setting!");
      break;
}

# 2. IDENTIFY the input schema format, defaulting to full OGD schema.
if (isset($_REQUEST["game_id"])) {
  $game_id = strtoupper($_REQUEST["game_id"]);
}
else if (isset($_REQUEST["app_id"])) {
  $game_id = strtoupper($_REQUEST["app_id"]);
}
else {
   die("FAIL: Request is missing game_id element!");
}

$schema_version = $_REQUEST["schema_version"] ?? "N/A";
switch ($schema_version) {
   case "1.0-alpha":
      $request_schema = OGD_SCHEMA_10;
      break;
   default:
      $request_schema = OGD_SCHEMA_01;
      if (in_array($game_id, LOGGER_GAMES)) {
         $request_schema = LOGGER_SCHEMA;
      }
      break;
}

# 3. DECODE the query data from raw input data.
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

# 4. PARSE and SEND data through a DB query.
if (count($data) > 0) {
   $query = new EventQuery($request_schema, $game_id, $data);

   switch ($db_type) {
      case "bigquery":
         $arr = $query->AsBigQuery($conn);
         $result = BigQueryUtils::Insert($conn, $game_id, $arr);
         break;
      case "mysql":
         $query_string = $query->AsMySQL($conn);
         // $result = MySQLUtils::Insert($conn, $game_id, $query_string);
         $result = "Dummy run of \n".$query_string."\n in MySQL.";
         error_log("Sending mysql response: ".$result);
         break;
      default:
         http_response_code(500);
         die("FAIL: API software was misconfigured, invalid db_type setting!");
         break;
   }
} else {
   http_response_code(400);
   die("FAIL: Could not log event(s), no valid data received!");
}

# 5. If successful, forward data to monitor and return.
// TODO : re-enable communication with an instance of the event monitor
// sendToMonitor($_REQUEST, $data[0]);
die("SUCCESS: " . $result);
?>