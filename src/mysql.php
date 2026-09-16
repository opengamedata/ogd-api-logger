<?php
  $LOGGER_SCHEMA = "LOGGER";
  $OGD_SCHEMA    = "OPENGAMEDATA";

   function generateQueryString($schema, $app_id, $data, $conn) {
      global $LOGGER_SCHEMA;
      global $OGD_SCHEMA;
      $vals = "";
      $n_rows = count($data);
      $cols = OGDInsert($app_id).OGDColumns();
      switch ($schema) {
         case $LOGGER_SCHEMA:
            for($i = 0; $i < $n_rows; $i++)
            {
               $vals .= LoggerToOGDValues($data[$i], $conn);
               if($i < $n_rows-1) {
                  $vals .= ",";
               }
            }
            return $cols.$vals;
            break;
         case $OGD_SCHEMA:
            for($i = 0; $i < $n_rows; $i++)
            {
               $vals .= OGDValues($data[$i], $conn);
               if($i < $n_rows-1) {
                  $vals .= ",";
               }
            }
            return $cols.$vals;
            break;
         default:
            error_log("Got schema name ".$schema." that did not match ".$LOGGER_SCHEMA." or ".$OGD_SCHEMA.", defaulting to ".$OGD_SCHEMA);
            for($i = 0; $i < $n_rows; $i++)
            {
               $vals .= OGDValues($data[$i], $conn);
               if($i < $n_rows-1) {
                  $vals .= ",";
               }
            }
            return $cols.$vals;
      }
   }

   function OGDInsert($app_id) : string {
      return "INSERT INTO ".$app_id." ";
   }

   function OGDColumns() : string {
      return "(".
      "session_id,".
      "user_id,".
      "user_data,".
      "client_time,".
      "client_time_ms,".
      "client_offset,".
      "server_time,".
      "event_name,".
      "event_data,".
      "event_source,".
      "game_state,".
      "app_version,".
      "app_branch,".
      "log_version,".
      "event_sequence_index,".
      "host,".
      "remote_addr,".
      "http_user_agent,".
      "synced".
      ") VALUES";
   }

   function LoggerToOGDValues($datum, $conn) : string {
      # 1. Get all the variables out of a Logger package.
      $app_version_raw = null;
      $session_id  = null;
      $persistent_session_id = null;
      $player_id   = null;
      $http_user_agent = mysqli_real_escape_string($conn,$_SERVER["HTTP_USER_AGENT"]);

      //per dump
      if(isset($_REQUEST["app_version"]))           $app_version_raw       = filter_var($_REQUEST["app_version"],           FILTER_SANITIZE_NUMBER_INT); else die("No app_version");
      if(isset($_REQUEST["session_id"]))            $session_id            = filter_var($_REQUEST["session_id"],            FILTER_SANITIZE_NUMBER_INT); else die("No session_id");
      if(isset($_REQUEST["persistent_session_id"])) $persistent_session_id = filter_var($_REQUEST["persistent_session_id"], FILTER_SANITIZE_NUMBER_INT);
      if(isset($_REQUEST["player_id"]))             $player_id             = preg_replace("/[^a-zA-Z0-9]+/", "", $_REQUEST["player_id"]);

      $level = 0;
      $event = "UNDEFINED";
      $event_custom = 0;
      $event_data_complex = NULL;
      $client_time = date("Y-m-d H:i:s");
      $client_time_ms = 0;
      $session_n      = -1;

      if(isset($datum->level)) {
         $level = filter_var($datum->level, FILTER_SANITIZE_NUMBER_INT);
      }
      if(isset($datum->event)) {
         $event = mysqli_real_escape_string($conn,$datum->event);
      }
      //optional
      if(isset($datum->event_custom)) {
         $event_custom = filter_var($datum->event_custom, FILTER_SANITIZE_NUMBER_INT);
      }
      if(isset($datum->event_data_complex)) {
         $event_data_complex = mysqli_real_escape_string($conn,$datum->event_data_complex);
      } else {
         $event_data_complex = "{}";
      }
      if(isset($datum->session_n)) {
         $session_n = filter_var($datum->session_n, FILTER_SANITIZE_NUMBER_INT);
      }
      if(isset($datum->client_time))
      {
         $client_time = mysqli_real_escape_string($conn,$datum->client_time);
         // $client_time is a string like "2019-02-20 17:21:05.493Z"
         $ct_len = strlen($client_time);
         $ct_dot = strrpos($client_time,".");
         if ($ct_dot) {
            // drop ".493Z" for the DATETIME, and extract 493 for separate column
            $client_time_ms = substr($client_time, $ct_dot + 1, $ct_len - ($ct_dot + 1) - 1);
            $client_time    = substr($client_time, 0, $ct_dot);
         } else {
            $client_time_ms = 0;
         }
      }
      # 2. Convert Logger stuff over to naming for an OGD package
      $user_id = $player_id;
      $user_data = mysqli_real_escape_string($conn, json_encode( ["persistent_session_id" => $persistent_session_id] ));
      $client_offset = null;
      $event_name = $event.".".$event_custom;
      $event_data = $event_data_complex;
      $game_state = mysqli_real_escape_string($conn, json_encode( ["level" => $level] ));
      $app_version = "1.0";
      $app_branch  = "main";
      $log_version = $app_version_raw;
      $event_sequence_index = $session_n;
      return generateValueString($session_id, $user_id,    $user_data,  $client_time, $client_time_ms, $client_offset,
                                 $event_name, $event_data, $game_state, $app_version, $app_branch,     $log_version,
                                 $event_sequence_index,    $http_user_agent);
   }

   function OGDValues($datum, $conn) : string {
   // Items from $_REQUEST: session_id, user_id, user_data, app_version, app_branch, log_version, 
   // Items from $datum: client_time, client_offset, event_name, event_data, game_state, event_sequence_index
   //per dump
      $user_id = NULL;   
      $user_data = NULL;
      $client_time = date("Y-m-d H:i:s");
      $client_time_ms = 0;
      $client_offset = "00:00:00";
      $event_data = NULL;
      $game_state = NULL;
      $app_branch = NULL;

      if(isset($_REQUEST["session_id"])) {
         $session_id = filter_var($_REQUEST["session_id"], FILTER_SANITIZE_NUMBER_INT);
      } else { die("No session_id"); }

      if(isset($_REQUEST["user_id"])) {
         $user_id = preg_replace("/[^a-zA-Z0-9]+/", "", $_REQUEST["user_id"]);
      }

      if(isset($_REQUEST["user_data"])) {
         $user_data = mysqli_real_escape_string($conn, $_REQUEST["user_data"]);
      } else {
         $user_data = "{}";
      }

      if(isset($datum->client_time))
      {
         $client_time = mysqli_real_escape_string($conn,$datum->client_time);
         // $client_time is a string like "2019-02-20 17:21:05.493Z"
         $ct_len = strlen($client_time);
         $ct_dot = strrpos($client_time,".");
         if ($ct_dot) {
            // drop ".493Z" for the DATETIME, and extract 493 for separate column
            $client_time_ms = substr($client_time, $ct_dot + 1, $ct_len - ($ct_dot + 1) - 1);
            $client_time = substr($client_time, 0, $ct_dot);
         } else {
            $client_time_ms = 0;
         }
      }

      if(isset($datum->client_offset)) {
         $client_offset = mysqli_real_escape_string($conn,$datum->client_offset);
      }

      if(isset($datum->event_name)) {
         $event_name = mysqli_real_escape_string($conn,$datum->event_name);
      } else { die("No event_name"); }

      if(isset($datum->event_data)) {
         $event_data = mysqli_real_escape_string($conn,$datum->event_data);
      } else {
         $event_data = "{}";
      }

      if(isset($datum->game_state)) {
         $game_state = mysqli_real_escape_string($conn,$datum->game_state);
      } else {
         $game_state = "{}";
      }

      if(isset($_REQUEST["app_version"])) {
         $app_version = filter_var($_REQUEST["app_version"], FILTER_SANITIZE_NUMBER_INT);
      } else { die("No app_version"); }

      if(isset($_REQUEST["app_branch"])) {
         $app_branch = preg_replace("/[^a-zA-Z0-9-_]+/", "", $_REQUEST["app_branch"]);
      }

      if(isset($_REQUEST["log_version"])) {
         $log_version = filter_var($_REQUEST["log_version"], FILTER_SANITIZE_NUMBER_INT);
      } else { die("No log_version"); }

      if(isset($datum->event_sequence_index)) {
         $event_sequence_index  = filter_var($datum->event_sequence_index, FILTER_SANITIZE_NUMBER_INT);
         // error_log("From datum ".json_encode($datum).", event sequence index is ".$datum->event_sequence_index);
      } else { die("No event_sequence_index"); }

      $http_user_agent = mysqli_real_escape_string($conn, $_SERVER["HTTP_USER_AGENT"]);

      return generateValueString($session_id, $user_id,    $user_data,  $client_time, $client_time_ms, $client_offset,
                                 $event_name, $event_data, $game_state, $app_version, $app_branch,     $log_version,
                                 $event_sequence_index, $http_user_agent);
   }

   function generateValueString(string $session_id,  ?string $user_id,        ?string $user_data,
                                string $client_time,  string $client_time_ms, ?string $client_offset,
                                string $event_name,  ?string $event_data,     ?string $game_state,
                                string $app_version, $app_branch,      string $log_version,
                                string $event_sequence_index, string $http_user_agent) : string
   {
      $server_time = "CURRENT_TIMESTAMP()";
      $event_data_str = !is_null($event_data) ? $event_data : "NULL";
      $event_source = "GAME";
      $host = $_SERVER['HTTP_HOST'];
      $remote_addr = $_SERVER["REMOTE_ADDR"];
      $synced      = 0;
      return "(".
         "\"".$session_id."\",".
         "\"".$user_id."\",".
         "\"".$user_data."\",".
         "\"".$client_time."\",".
         "\"".$client_time_ms."\",".
         (!is_null($client_offset) ? "\"".$client_offset."\"" : "NULL").",".
         "".$server_time.",".
         "\"".$event_name."\",".
         "\"".$event_data_str."\",".
         "\"".$event_source."\",".
         "\"".$game_state."\",".
         "\"".$app_version."\",".
         "\"".$app_branch."\",".
         "\"".$log_version."\",".
         "\"".$event_sequence_index."\",".
         "\"".$host."\",".
         "\"".$remote_addr."\",".
         "\"".$http_user_agent."\",".
         "\"".$synced."\"".
      ")";
   }

?>
