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

# 1. Figure out what the input schema looks like, defaulting to full OGD schema.
$REQUEST_SCHEMA = $OGD_SCHEMA;
$APP_ID = "NO APP ID";
if (isset($_REQUEST["app_id"])) {
  $APP_ID = strtoupper($_REQUEST["app_id"]);
  // error_log("The app id in upper-case is: ".$upper);
  $logger_games = array("BACTERIA",   "BALLOON",  "CRYSTAL",    "CYCLE_CARBON", "CYCLE_NITROGEN", "CYCLE_WATER",
                        "EARTHQUAKE", "JOWILDER", "LAKELAND",   "MAGNET",       "WAVES",          "WIND");
  $ogd_games    = array("AQUALAB",    "BLOOM",    "ICECUBE",    "JOURNALISM",   "MASHOPOLIS",     "PENGUINS",
                        "THERMOVR",   "TRANSFORMATION_QUEST");
  if (in_array($APP_ID, $logger_games)) {
    $REQUEST_SCHEMA = $LOGGER_SCHEMA;
  }
  elseif (in_array($APP_ID, $ogd_games)) {
    $REQUEST_SCHEMA = $OGD_SCHEMA;
  }
}

# 2. Make the db connection before we go to the trouble of generating query.
switch ($db_type) {
   case "bigquery":
      $conn = new BigQueryClient();
      break;
   case "mysql":
      $conn = mysqli_connect($servername, $username, $password, $db);
      if (!$conn) {
         die("FAIL: Could not connect to the database.\nError message: " . mysqli_connect_error());
      }
      break;
   default:
      die("FAIL: API software was misconfigured, invalid db_type setting!");
      break;
}

# 3. Generate the query data from raw input data.
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
   $query = new EventQuery($REQUEST_SCHEMA, $APP_ID, $data);
   $q_string = $query->AsQueryString($db_type, $conn);

   switch ($db_type) {
      case "bigquery":
         BigQueryUtils::InsertBigQuery($APP_ID);
         break;
      case "mysql":
         MySQLUtils::InsertMySQL($conn, $APP_ID, $q_string);
         break;
      default:
         die("FAIL: API software was misconfigured, invalid db_type setting!");
         break;
   }
} else {
   die("FAIL: Could not log event(s), no valid data received!");
}

# 5. If successful, forward data to monitor and return.
sendToMonitor($_REQUEST, $data[0]);
die("SUCCESS: " . $query);

die("Logger endpoint reached, version=".$loggerversion);
?>