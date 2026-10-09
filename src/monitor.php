<?php
/**
 * Module for forwarding data to the monitor service.
 */
function sendToMonitor($paramArray, $bodyObject) {
   global $monitorEnabled;

   if ($monitorEnabled) {
      $loggerData = combineParamsAndBody($paramArray, $bodyObject);
      // $start_time_milliseconds = round(microtime(true) * 1000);
      // syslog(LOG_NOTICE, "Sending data to monitor, beforeTime:".$start_time_milliseconds);
      // error_log("Repeat message with error_log: Sending data to monitor, beforeTime:".$start_time_milliseconds);
      transmitToMonitor($loggerData);
      // $end_time_milliseconds = round(microtime(true) * 1000);
      // syslog(LOG_NOTICE, "Sent data to monitor, timedelta:".($end_time_milliseconds - $start_time_milliseconds));
      // error_log("Repeat message with error_log: Sent data to monitor, timedelta:".($end_time_milliseconds - $start_time_milliseconds));
      // if ($REQUEST_SCHEMA != $OGD_SCHEMA) {
      //   syslog(LOG_WARNING, "Warning: Got an old-logger data format");
      // }
   }
}

// connect to flask app with flask api url
// send json package to flask app
function transmitToMonitor($jsonPackage)
{
    global $monitorURL, $monitorTimeout;
    global $loggerVersion;

   $jsonPackage["ogd_logger_version"] = $loggerVersion;
   $ch = curl_init('https://'.$monitorURL.'/log/event');
   $headers = array(
      'Content-Type: application/json',
      'Content-Length: ' . strlen($jsonPackage)
   );
   curl_setopt($ch, CURLOPT_PORT, 443);
   curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
   curl_setopt($ch, CURLOPT_POST, true);
   curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPackage);
   curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 50); // DEPLOYMENT CHANGE
   curl_setopt($ch, CURLOPT_TIMEOUT_MS, $monitorTimeout);
   // syslog(LOG_NOTICE, 'Sending packet to monitor API at '.$monitorURL.' : ' . $jsonPackage );
   $response = curl_exec($ch);

   // check for cURL errors
   if (curl_errno($ch)) {
      error_log( 'cURL error when attempting to communicate with Monitor API: ' . curl_error($ch) );
   }
   // } else {
      // TODO : Comment this out once we have the thing working.
      // syslog(LOG_NOTICE, 'Response from Monitor API: ' . $response );
   // }

   // close cURL session
   curl_close($ch);
}

// given <parameter array from $_REQUEST> AND <body object from $data>
// iterate these two and return a combined <jsonPackage> including each item of both
function combineParamsAndBody($paramArray, $bodyObject)
{
   $ret_val = array();

   foreach ($paramArray as $key => $value) {
      $ret_val[$key] = $value;
   }

   foreach ($bodyObject as $key => $value) {
      $ret_val[$key] = $value;
   }

   // remove the elements you do not want to send through log.php to flaskapp
   // unset($ret_val["data"]); // contains long string of encoded data
   unset($ret_val["remote_addr"]);
   unset($ret_val["http_user_agent"]);

   return json_encode($ret_val);
}
?>