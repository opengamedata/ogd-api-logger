<?php
header("Access-Control-Allow-Headers: Origin, Authorization, X-Requested-With, Content-Type, Accept");
header("Access-Control-Allow-Origin: *");

include('config.php');
// include('query_generator.php');
// include('monitor.php');
# 1. Figure out what the input schema looks like, defaulting to full OGD schema.
// $REQUEST_SCHEMA = $OGD_SCHEMA;
// $APP_ID = "NO APP ID";
// if (isset($_REQUEST["app_id"])) {
//   $APP_ID = strtoupper($_REQUEST["app_id"]);
//   // error_log("The app id in upper-case is: ".$upper);
//   $logger_games = array("BACTERIA",   "BALLOON",  "CRYSTAL",    "CYCLE_CARBON", "CYCLE_NITROGEN", "CYCLE_WATER",
//                         "EARTHQUAKE", "JOWILDER", "LAKELAND",   "MAGNET",       "WAVES",          "WIND");
//   $ogd_games    = array("AQUALAB",    "BLOOM",    "ICECUBE",    "JOURNALISM",   "MASHOPOLIS",     "PENGUINS",
//                         "THERMOVR",   "TRANSFORMATION_QUEST");
//   if (in_array($APP_ID, $logger_games)) {
//     $REQUEST_SCHEMA = $LOGGER_SCHEMA;
//   }
//   elseif (in_array($APP_ID, $ogd_games)) {
//     $REQUEST_SCHEMA = $OGD_SCHEMA;
//   }
// }

# 2. Make the db connection before we go to the trouble of generating query.
// $conn = mysqli_connect($servername, $username, $password, $db);
// if (!$conn) {
//   die("FAIL: Could not connect to the database.\nError message: " . mysqli_connect_error());
// }

# 3. Generate the query data from raw input data.
/**
 * An array of event data bodies, containing event_name, event_data, and similar columns.
 * @var array
 */
// $data = json_decode(base64_decode($_POST["data"]));
// if (!is_array($data)) {
//   $d = $data;
//   $data = array();
//   array_push($data, $d);
// }

// # 4. Send the query itself. Log errors if failed.
// if (count($data) > 0) {
//   $query = generateQueryString($REQUEST_SCHEMA, $APP_ID, $data, $conn);
//   $result = mysqli_query($conn, $query);
//   if ($result) {
//     if ($monitorEnabled) {
// # 5. Make flask monitor connection after connecting to db
//       $loggerData = combineParamsAndBody($_REQUEST, $data[0]);
//       $start_time_milliseconds = round(microtime(true) * 1000);
//       // syslog(LOG_NOTICE, "Sending data to monitor, beforeTime:".$start_time_milliseconds);
//       // error_log("Repeat message with error_log: Sending data to monitor, beforeTime:".$start_time_milliseconds);
//       sendToMonitor($loggerData);
//       $end_time_milliseconds = round(microtime(true) * 1000);
//       // syslog(LOG_NOTICE, "Sent data to monitor, timedelta:".($end_time_milliseconds - $start_time_milliseconds));
//       // error_log("Repeat message with error_log: Sent data to monitor, timedelta:".($end_time_milliseconds - $start_time_milliseconds));
//       // if ($REQUEST_SCHEMA != $OGD_SCHEMA) {
//       //   syslog(LOG_WARNING, "Warning: Got an old-logger data format");
//       // }
//     }
//   } else {
//     $sql_err = "Query for ".$APP_ID." failed with error: ".mysqli_error($conn);
//     error_log($sql_err);
//     die("FAIL: ".$sql_err);
//   }
// } else {
//   error_log("Didn't perform query, n_rows is <= 0");
// }
// die("SUCCESS: " . $query);
die("Logger endpoint reached, version=".$loggerversion);
?>