<?php

   $LOGGER_SCHEMA = "LOGGER";
   $OGD_SCHEMA    = "OPENGAMEDATA";

   class EventQuery {
      const string OGD_COLUMNS =
      "(".
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

      private array $events;
      private string $app_id;

      function __construct($schema, $app_id, $data)
      {
         global $LOGGER_SCHEMA;
         global $OGD_SCHEMA;
         $n_rows = count($data);

         $this->app_id = $app_id;
         switch ($schema) {
            case $LOGGER_SCHEMA:
               $lambda = fn($datum) => Event::FromLoggerFormat($datum);
               break;
            case $OGD_SCHEMA:
               $lambda = fn($datum) => Event::FromOGDFormat($datum);
               break;
            default:
               error_log("Got schema name ".$schema." that did not match ".$LOGGER_SCHEMA." or ".$OGD_SCHEMA.", defaulting to ".$OGD_SCHEMA);
               $lambda = fn($datum) => Event::FromOGDFormat($datum);
               break;
         }
         $this->events = array_map($lambda, $data);
      }

      function AsBigQuery() {
         $lambda = fn(Event $next_event) => [ 'data' => $next_event->AsBigQuery($conn) ];
         return array_map($lambda, $this->events);
      }

      function AsMySQL($db_type, $conn) : string
      {
         $lambda = fn(Event $next_event) => $next_event->AsMySQLQuery($conn);
         $cols = "INSERT INTO ".$this->app_id." ".EventQuery::OGD_COLUMNS;
         $vals = join(",", array_map($lambda, $this->events));
         return $cols.$vals;
      }

   }

   class Event {

      private static string $event_source = "GAME";
      private static int $synced = 0;
      private string $session_id;
      private ?string $user_id;
      private ?string $user_data;
      private string $client_time;
      private string $client_time_ms;
      private ?string $client_offset;
      private string $event_name;
      private ?string $event_data;
      private ?string $game_state;
      private string $app_version;
      private string $app_branch;
      private string $log_version;
      private string $event_sequence_index;
      private string $http_user_agent;
      private string $server_time;
      private string $host;
      private string $remote_addr;

      function __construct(string $session_id,  ?string $user_id,        ?string $user_data,
                           string $client_time,  string $client_time_ms, ?string $client_offset,
                           string $event_name,  ?string $event_data,     ?string $game_state,
                           string $app_version, $app_branch,      string $log_version,
                           string $event_sequence_index, string $http_user_agent)
      {
         $this->session_id = $session_id;
         $this->user_id = $user_id;
         $this->user_data = $user_data;
         $this->client_time = $client_time;
         $this->client_time_ms = $client_time_ms;
         $this->client_offset = $client_offset;
         $this->event_name = $event_name;
         $this->event_data = $event_data;
         $this->game_state = $game_state;
         $this->app_version = $app_version;
         $this->app_branch = $app_branch;
         $this->log_version = $log_version;
         $this->event_sequence_index = $event_sequence_index;
         $this->http_user_agent = $http_user_agent;
         $this->server_time = "CURRENT_TIMESTAMP()";
         $this->host = $_SERVER['HTTP_HOST'];
         $this->remote_addr = $_SERVER["REMOTE_ADDR"];
      }

      static function FromOGDFormat($datum) : Event
      /** Create an Event object from the standard OGD format (Schema v0.1)
       * 
       * Items from $_REQUEST: session_id, user_id, user_data, app_version, app_branch, log_version, 
       * Items from $datum: client_time, client_offset, event_name, event_data, game_state, event_sequence_index
       */
      {
         // per dump
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
            $user_data = $_REQUEST["user_data"];
         } else {
            $user_data = "{}";
         }

         if(isset($datum->client_time))
         {
            $client_time = $datum->client_time;
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
            $client_offset = $datum->client_offset;
         }

         if(isset($datum->event_name)) {
            $event_name = $datum->event_name;
         } else { die("No event_name"); }

         if(isset($datum->event_data)) {
            $event_data = $datum->event_data;
         } else {
            $event_data = "{}";
         }

         if(isset($datum->game_state)) {
            $game_state = $datum->game_state;
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

         $http_user_agent = $_SERVER["HTTP_USER_AGENT"];

         return new Event($session_id, $user_id,    $user_data,  $client_time, $client_time_ms, $client_offset,
                          $event_name, $event_data, $game_state, $app_version, $app_branch,     $log_version,
                          $event_sequence_index, $http_user_agent);
      }

      static function FromLoggerFormat($datum) : Event
      /** Create an Event object from the legacy "Old Logger" format.
       * 
       * Items from $_REQUEST: session_id, persistent_session_id, app_version, player_id
       * Items from $datum: level, event, event_custom, event_data_complex, session_n, client_time
       */
      {
         # 1. Get all the variables out of a Logger package.
         $app_version_raw = null;
         $session_id  = null;
         $persistent_session_id = null;
         $player_id   = null;
         $http_user_agent = $_SERVER["HTTP_USER_AGENT"];

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
            $event = $datum->event;
         }
         //optional
         if(isset($datum->event_custom)) {
            $event_custom = filter_var($datum->event_custom, FILTER_SANITIZE_NUMBER_INT);
         }
         if(isset($datum->event_data_complex)) {
            $event_data_complex = $datum->event_data_complex;
         } else {
            $event_data_complex = "{}";
         }
         if(isset($datum->session_n)) {
            $session_n = filter_var($datum->session_n, FILTER_SANITIZE_NUMBER_INT);
         }
         if(isset($datum->client_time))
         {
            $client_time = $datum->client_time;
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
         $user_data = json_encode( ["persistent_session_id" => $persistent_session_id] );
         $client_offset = null;
         $event_name = $event.".".$event_custom;
         $event_data = $event_data_complex;
         $game_state = json_encode( ["level" => $level] );
         $app_version = "1.0";
         $app_branch  = "main";
         $log_version = $app_version_raw;
         $event_sequence_index = $session_n;
         return new Event($session_id, $user_id,    $user_data,  $client_time, $client_time_ms, $client_offset,
                          $event_name, $event_data, $game_state, $app_version, $app_branch,     $log_version,
                          $event_sequence_index,    $http_user_agent);
      }

      static function AsMySQLQuery($conn) : string
      {
         $offset         = !is_null($this->client_offset) ? "\"".mysqli_real_escape_string($conn, $this->client_offset)."\"" : "NULL";
         $event_data_str = !is_null($this->event_data)    ?      mysqli_real_escape_string($conn, $this->event_data)         : "NULL";
         return "(".
            "\"".mysqli_real_escape_string($conn, $this->session_id)."\",".
            "\"".mysqli_real_escape_string($conn, $this->user_id)."\",".
            "\"".mysqli_real_escape_string($conn, $this->user_data)."\",".
            "\"".mysqli_real_escape_string($conn, $this->client_time)."\",".
            "\"".mysqli_real_escape_string($conn, $this->client_time_ms)."\",".
            "".$offset.",".
            "".$this->server_time.",".
            "\"".mysqli_real_escape_string($conn, $this->event_name)."\",".
            "\"".$event_data_str."\",".
            "\"".Event::$event_source."\",".
            "\"".mysqli_real_escape_string($conn, $this->game_state)."\",".
            "\"".mysqli_real_escape_string($conn, $this->app_version)."\",".
            "\"".mysqli_real_escape_string($conn, $this->app_branch)."\",".
            "\"".mysqli_real_escape_string($conn, $this->log_version)."\",".
            "\"".mysqli_real_escape_string($conn, $this->event_sequence_index)."\",".
            "\"".mysqli_real_escape_string($conn, $this->host)."\",".
            "\"".mysqli_real_escape_string($conn, $this->remote_addr)."\",".
            "\"".mysqli_real_escape_string($conn, $this->http_user_agent)."\",".
            "\"".Event::$synced."\"".
         ")";
      }

      static function AsBigQuery($conn) : string
      {
         return [
            "session_id" => $this->session_id,
            "user_id" => $this->user_id,
            "user_data" => $this->user_data,
            "client_time" => $this->client_time,
            // "client_time_ms" => $this->client_time_ms,
            "client_offset" => $this->client_offset,
            "event_name" => $this->event_name,
            "event_data" => $this->event_data,
            "event_source" => $this->event_source,
            // "synced" => $this->synced,
            "game_state" => $this->game_state,
            "app_version" => $this->app_version,
            "app_branch" => $this->app_branch,
            "log_version" => $this->log_version,
            "event_sequence_index" => $this->event_sequence_index,
            "host" => $this->host,
            "remote_addr" => $this->remote_addr
         ];
      }
   }

?>
