<?php
header("Access-Control-Allow-Headers: Origin, Authorization, X-Requested-With, Content-Type, Accept");
header("Access-Control-Allow-Origin: *");

# 3rd-party imports
require 'vendor/autoload.php';
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\Logging\LoggingClient;
# Local imports
require 'config.php';
require 'bigquery.php';
require 'mysql.php';
require 'query.php';
require 'monitor.php';

$LOGGER_GAMES = array("BACTERIA",   "BALLOON",  "CRYSTAL",    "CYCLE_CARBON", "CYCLE_NITROGEN", "CYCLE_WATER",
                     "EARTHQUAKE", "JOWILDER", "LAKELAND",   "MAGNET",       "WAVES",          "WIND");

$log_client = new LoggingClient();
$logger = $log_client->psrLogger("debug-logging");

$logger->info("With db type ".$db_type."\n");
# 1. Make the db connection before we go to the trouble of looking at the data.
switch ($db_type) {
   case "bigquery":
      // $conn = new BigQueryClient([ 'projectId' => $db ]);
      $logger->info("Dummy connect to BQ\n");
      break;
   case "mysql":
      // $conn = mysqli_connect($servername, $username, $password, $db);
      // if (!$conn) {
      //    die("FAIL: Could not connect to the database.\nError message: " . mysqli_connect_error());
      // }
      $logger->info("Dummy connect to MySQL\n");
      break;
   default:
      die("FAIL: API software was misconfigured, invalid db_type setting!");
      break;
}

# 2. Figure out what the input schema looks like, defaulting to full OGD schema.
$request_schema = $OGD_SCHEMA;
$app_id = "NO APP ID";

if (isset($_REQUEST["app_id"])) {
  $app_id = strtoupper($_REQUEST["app_id"]);
  if (in_array($APP_ID, $LOGGER_GAMES)) {
    $request_schema = $LOGGER_SCHEMA;
  }
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
   $query = new EventQuery($request_schema, $app_id, $data);

   switch ($db_type) {
      case "bigquery":
         $arr = $query->AsBigQuery();
         // $result = BigQueryUtils::Insert($conn, $app_id, $arr);
         $result = "Dummy insert of ".count($arr)." events into BQ.";
         $logger->info("Sending bigquery response: ".$result);
         break;
      case "mysql":
         $query_string = $query->AsMySQL($db_type, $app_id, $conn);
         // $result = MySQLUtils::Insert($conn, $app_id, $query_string);
         $result = "Dummy run of \n".$query_string."\n in MySQL.";
         $logger->info("Sending mysql response: ".$result);
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