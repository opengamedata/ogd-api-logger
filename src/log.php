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

# 1. CONNECT to DB before we go to the trouble of looking at the data.
switch ($db_type) {
   case "bigquery":
      try {
         $conn = new BigQueryClient();
         $test_datasets = $conn->datasets(["resultLimit" => 1]);
         if ($test_datasets->current()) {
            error_log("Made connection to BQ project, containing dataset ".$test_datasets->current()->id()."\n");
         }
         else {
            http_response_code(500);
            die("Could not find any datasets when connecting to event database.");
         }
      }
      catch (Exception $e) {
         http_response_code(500);
         error_log("Exception when trying to connect to BigQuery: ".$e->getMessage());
         die("Could not connect to event database");
      }
      break;
   case "mysql":
      // $conn = mysqli_connect($servername, $username, $password, $db);
      // if (!$conn) {
      //    die("FAIL: Could not connect to the database.\nError message: " . mysqli_connect_error());
      // }
      die("FAIL: Logging with MySQL is currently not supported!");
      break;
   default:
      die("FAIL: API software was misconfigured, invalid db_type setting!");
      break;
}

# 2. IDENTIFY the input schema format, defaulting to full OGD schema.
$request_schema = $OGD_SCHEMA;
$app_id = "NO APP ID";

if (isset($_REQUEST["app_id"])) {
  $app_id = strtoupper($_REQUEST["app_id"]);
  if (in_array($app_id, $LOGGER_GAMES)) {
    $request_schema = $LOGGER_SCHEMA;
  }
}
else {
   die("FAIL: Request is missing app_id!");
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
   $query = new EventQuery($request_schema, $app_id, $data);

   switch ($db_type) {
      case "bigquery":
         $arr = $query->AsBigQuery($conn);
         $result = BigQueryUtils::Insert($conn, $app_id, $arr);
         break;
      case "mysql":
         $query_string = $query->AsMySQL($db_type, $app_id, $conn);
         // $result = MySQLUtils::Insert($conn, $app_id, $query_string);
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