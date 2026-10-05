<?php

   const LOGGER_SCHEMA = "LOGGER";
   const OGD_SCHEMA_01 = "OPENGAMEDATA_V0";
   const OGD_SCHEMA_10 = "OPENGAMEDATA_V1";

   class EventQuery {
      const string OGD_01_COLUMNS =
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
         $n_rows = count($data);

         $this->app_id = $app_id;
         switch ($schema) {
            case LOGGER_SCHEMA:
               $lambda = fn($datum) => Event::FromLoggerLegacyFormat($app_id, $datum);
               break;
            case OGD_SCHEMA_01:
               $lambda = fn($datum) => Event::FromOGDLegacyFormat($app_id, $datum);
               break;
            case OGD_SCHEMA_10:
               $lambda = fn($datum) => Event::FromOGDStandardFormat($app_id, $datum);
               break;
            default:
               error_log("Got schema name ".$schema." that did not match ".LOGGER_SCHEMA." or ".OGD_SCHEMA_01." or ".OGD_SCHEMA_10.", defaulting to ".OGD_SCHEMA_10);
               $lambda = fn($datum) => Event::FromOGDStandardFormat($app_id, $datum);
               break;
         }
         $this->events = array_map($lambda, $data);
      }

      function AsBigQuery() {
         $lambda = fn(Event $next_event) => [ 'data' => $next_event->AsBigQuery() ];
         return array_map($lambda, $this->events);
      }

      function AsMySQL($conn) : string
      {
         $lambda = fn(Event $next_event) => $next_event->AsMySQLQuery($conn);
         $cols = "INSERT INTO ".$this->app_id." ".EventQuery::OGD_01_COLUMNS;
         $vals = join(",", array_map($lambda, $this->events));
         return $cols.$vals;
      }

   }

   class Event {

      # Category 0 Data: Non-logged data
      private static int $synced = 0;
      private static ?string $http_user_agent = null; // DEPRECATED
      private static ?string $host = null; // DEPRECATED
      private static ?string $remote_addr = null; // DEPRECATED

      # Category 1 Data: Identification
      private  string $game_id;
      private ?string $instance_id;
      private ?string $player_id;
      private  string $session_id;
      # Category 2 Data: Sequencing
      // TODO : sort out timestamp stuff, temporarily assuming client_time is our timestamp
      private  string $timestamp;
      private  string $game_time;
      private static string $server_time = "CURRENT_TIMESTAMP()"; // DEPRECATED
      private  int $session_sequence_index; // TODO : this is listed as non-required in standard, that's probably wrong.
      # Category 3 Data: Segmenting
      private ?string $game_segment;
      # Category 4 Data: Provenance
      private static string $event_source = "GAME";
      # Category 5 Data: Versioning
      private  string $source_version;
      private  string $game_version;
      private  string $schema_version;
      private  string $log_version;
      # Category 6 Data: Configuration
      private ?string $condition;
      private ?string $game_configuration;
      private ?string $platform;
      # Category 7 Data: Context
      private ?string $game_state;
      private ?string $player_history;
      # Category 8 Data: Event
      private  string $event_id;
      private ?string $event_name;
      private  string $event_data;
      # Category 9 Data: Private
      // Nothing here for now, in discussion for final standard

      function __construct(string $game_id,        ?string $instance_id,    ?string $player_id,     string $session_id,
                           string $timestamp,      string $game_time,
                           string $sequence_index, ?string $game_segment,
                           string $game_version,   string $schema_version,  string $log_version,
                           ?string $condition,     ?string $game_config,    ?string $platform,
                           ?string $game_state,    ?string $player_history,
                           int $event_id,          ?string $event_name,     string $event_data,
                          )
      {
         $this->game_id = $game_id;
         $this->instance_id = $instance_id;
         $this->player_id = $player_id;
         $this->session_id = $session_id;

         $this->timestamp = $timestamp;
         $this->game_time = $game_time;
         $this->session_sequence_index = $sequence_index;

         $this->game_segment = $game_segment;

         $this->source_version = $game_version; // Game is always the source here.
         $this->game_version = $game_version;
         $this->schema_version = $schema_version;
         $this->log_version = $log_version;

         $this->condition = $condition;
         $this->game_configuration = $game_config;
         $this->platform = $platform;

         $this->player_history = $player_history;
         $this->game_state = $game_state;

         $this->event_id = $event_id;
         $this->event_name = $event_name;
         $this->event_data = $event_data;
      }

      static function FromOGDStandardFormat($game_id, $datum) : Event
      /** Create an Event object from the standard OGD format (Schema v1.0)
       * 
       * Items from $_REQUEST:
       * - app_id (technically getting this as input)
       * - instance_id
       * - player_id
       * - session_id
       * - game_version
       * - schema_version
       * - log_version
       * - condition
       * - game_configuration
       * - platform
       * - user_history
       * 
       * Items from $datum:
       * - timestamp
       * - authoritative_timestamp
       * - game_time
       * - session_sequence_index
       * - game_segment
       * - game_state
       * - event_id
       * - event_name
       * - event_data
       */
      {
         // per dump

         # Category 1 Data: Identification

         $instance_id = isset($_REQUEST["instance_id"]) ? preg_replace("/[^a-zA-Z0-9]+/", "", $_REQUEST["instance_id"]) : NULL;
         $player_id   = isset($_REQUEST["player_id"])   ? preg_replace("/[^a-zA-Z0-9]+/", "", $_REQUEST["player_id"])   : NULL;
         if(isset($_REQUEST["session_id"])) {
            $session_id = filter_var($_REQUEST["session_id"], FILTER_SANITIZE_NUMBER_INT);
         } else { die("No session_id"); }

         # Category 2 Data: Sequencing

         $timestamp = date("Y-m-d H:i:s\\T");
         if(isset($datum->timestamp) && (DateTimeImmutable::createFromFormat('Y-m-d\Th:i:s.uT', $datum->timestamp) !== false))
         {
            $timestamp = $datum->timestamp;
         }

         $auth_timestamp = date("Y-m-d H:i:s\\T");
         if(isset($datum->authoritative_timestamp) && (DateTimeImmutable::createFromFormat('Y-m-d\Th:i:s.uT', $datum->authoritative_timestamp) !== false))
         {
            $auth_timestamp = $datum->authoritative_timestamp;
         }

         $game_time = "00:00:00.0000"; // Don't have a great default here
         if(isset($datum->timestamp) && (DateTimeImmutable::createFromFormat('h:i:s.u', $datum->game_time) !== false))
         {
            $game_time = $datum->game_time;
         }

         if(isset($datum->session_sequence_index)) {
            $session_sequence_index  = filter_var($datum->session_sequence_index, FILTER_SANITIZE_NUMBER_INT);
            // error_log("From datum ".json_encode($datum).", event sequence index is ".$datum->session_sequence_index);
         } else { die("No event_sequence_index"); }

         # Category 3 Data: Segmenting

         $segment = $datum->game_segment ?? "{}";
         if (!json_validate($segment)) {
            die("Invalid JSON in game_segment! ".json_last_error()." : ".json_last_error_msg());
         }

         # Category 4 Data: Provenance

         // Static, nothing to set here

         # Category 5 Data: Versioning

         // source_version is given the game_version

         if(isset($_REQUEST["game_version"])) {
            $game_version = filter_var($_REQUEST["game_version"], FILTER_SANITIZE_NUMBER_INT);
         } else { die("No game_version"); }

         $schema_version = $_REQUEST["schema_version"] ?? "N/A";

         if(isset($_REQUEST["log_version"])) {
            $log_version = filter_var($_REQUEST["log_version"], FILTER_SANITIZE_NUMBER_INT);
         } else { die("No log_version"); }

         # Category 6 Data: Configuration

         $condition = NULL;
         if(isset($_REQUEST["condition"])) {
            $condition = preg_replace("/[^a-zA-Z0-9-_]+/", "", $_REQUEST["condition"]);
         }

         $game_config = $_REQUEST["game_configuration"] ?? "{}";
         if (!json_validate($game_config)) {
            die("Invalid JSON in game_configuration! ".json_last_error()." : ".json_last_error_msg());
         }

         $platform = $_REQUEST["platform"] ?? "{}";
         if (!json_validate($platform)) {
            die("Invalid JSON in platform! ".json_last_error()." : ".json_last_error_msg());
         }

         # Category 7 Data: Context

         $game_state = $datum->game_state ?? "{}";
         if (!json_validate($game_state)) {
            die("Invalid JSON in game_state! ".json_last_error()." : ".json_last_error_msg());
         }

         $player_history = $_REQUEST["player_history"] ?? "{}";
         if (!json_validate($player_history)) {
            die("Invalid JSON in player_history! ".json_last_error()." : ".json_last_error_msg());
         }

         # Category 8 Data: Event
         if(isset($datum->event_id)) {
            $event_id = (int) filter_var($datum->event_id, FILTER_SANITIZE_NUMBER_INT);
         } else { die("No event_id"); }

         $event_name = $datum->event_name ?? "unnamed";

         if (isset($datum->event_data)) {
            $event_data = $datum->event_data;
            if (!json_validate($event_data)) {
               die("Invalid JSON in event_data! ".json_last_error()." : ".json_last_error_msg());
            }
         }
         else {
            die("No event_data");
         }

         # Category 9 Data: Private

         return new Event(
            game_id:$game_id,                       instance_id:$instance_id,             player_id:$player_id,     session_id:$session_id,
            timestamp:$timestamp,                   game_time:$game_time,
            sequence_index:$session_sequence_index, game_segment:null,
            game_version:$game_version,             schema_version:$schema_version,       log_version:$log_version,
            condition:$condition,                   game_config:null,                     platform:null,
            game_state:$game_state,                 player_history:$player_history,
            event_id:$event_id,                     event_name:$event_name,               event_data:$event_data,     
         );
      }

      static function FromOGDLegacyFormat($game_id, $datum) : Event
      /** Create an Event object from the original/legacy OGD format (Schema v0.1)
       * 
       * Items from $_REQUEST: session_id, user_id, user_data, app_version, app_branch, log_version, 
       * Items from $datum: client_time, client_offset, event_name, event_data, game_state, event_sequence_index
       */
      {
         // per dump
         $player_id = NULL;   
         $player_history = NULL;
         $client_time = date("Y-m-d H:i:s");
         $client_time_ms = 0;
         $client_offset = "00:00:00";
         $event_data = NULL;
         $game_state = NULL;
         $condition = NULL;

         # Category 1 Data: Identification

         if(isset($_REQUEST["user_id"])) {
            $player_id = preg_replace("/[^a-zA-Z0-9]+/", "", $_REQUEST["user_id"]);
         }

         if(isset($_REQUEST["session_id"])) {
            $session_id = filter_var($_REQUEST["session_id"], FILTER_SANITIZE_NUMBER_INT);
         } else { die("No session_id"); }

         # Category 2 Data: Sequencing

         if(isset($datum->client_time))
         {
            $client_time = $datum->client_time;
         }

         $game_time = "00:00:00.0000"; // Don't have a great default here

         $client_offset = "00:00";
         if(isset($datum->client_offset)) {
            $client_offset = $datum->client_offset;
         }

         if(isset($datum->event_sequence_index)) {
            $session_sequence_index  = filter_var($datum->event_sequence_index, FILTER_SANITIZE_NUMBER_INT);
            // error_log("From datum ".json_encode($datum).", event sequence index is ".$datum->session_sequence_index);
         } else { die("No event_sequence_index"); }

         # Category 3 Data: Segmenting

         // Not in 0.1

         # Category 4 Data: Provenance

         // Static, nothing to set here

         # Category 5 Data: Versioning

         if(isset($_REQUEST["app_version"])) {
            $game_version = filter_var($_REQUEST["app_version"], FILTER_SANITIZE_NUMBER_INT);
         } else { die("No app_version"); }

         $schema_version = "0.1";

         if(isset($_REQUEST["log_version"])) {
            $log_version = filter_var($_REQUEST["log_version"], FILTER_SANITIZE_NUMBER_INT);
         } else { die("No log_version"); }

         # Category 6 Data: Configuration

         if(isset($_REQUEST["app_branch"])) {
            $condition = preg_replace("/[^a-zA-Z0-9-_]+/", "", $_REQUEST["app_branch"]);
         }

         # Category 7 Data: Context

         if(isset($_REQUEST["user_data"])) {
            $player_history = $_REQUEST["user_data"];
         } else {
            $player_history = "{}";
         }

         if(isset($datum->game_state)) {
            $game_state = $datum->game_state;
         } else {
            $game_state = "{}";
         }

         # Category 8 Data: Event
         $event_id = 9999;

         if(isset($datum->event_name)) {
            $event_name = $datum->event_name;
         } else { die("No event_name"); }

         if(isset($datum->event_data)) {
            $event_data = $datum->event_data;
         } else {
            $event_data = "{}";
         }

         # Category 9 Data: Private

         $timestamp = $client_time."+".$client_offset;
         return new Event(
            game_id:$game_id,           instance_id:null,               player_id:$player_id,     session_id:$session_id,
            timestamp:$timestamp,       game_time:$game_time,           sequence_index:$session_sequence_index,
            game_segment:null,
            game_version:$game_version, schema_version:$schema_version, log_version:$log_version,
            condition:$condition,       game_config:null,               platform:null,
            game_state:$game_state,     player_history:$player_history,
            event_id:$event_id,         event_name:$event_name,         event_data:$event_data,     
         );
      }

      static function FromLoggerLegacyFormat($game_id, $datum) : Event
      /** Create an Event object from the legacy "Old Logger" format.
       * 
       * Items from $_REQUEST: session_id, persistent_session_id, app_version, player_id
       * Items from $datum: level, event, event_custom, event_data_complex, session_n, client_time
       */
      {

         # Category 1 Data: Identification

         $session_id  = null;
         if(isset($_REQUEST["session_id"])) $session_id = filter_var($_REQUEST["session_id"], FILTER_SANITIZE_NUMBER_INT); else die("No session_id");
         $player_id   = null;
         if(isset($_REQUEST["player_id"]))  $player_id  = preg_replace("/[^a-zA-Z0-9]+/", "", $_REQUEST["player_id"]);

         # Category 2 Data: Sequencing

         $client_time = date("Y-m-d H:i:s");
         if(isset($datum->client_time))
         {
            $client_time = $datum->client_time."+00:00";
         }

         $game_time = "00:00:00.0000"; // Don't have a great default here

         $session_n      = -1;
         if(isset($datum->session_n)) {
            $session_n = filter_var($datum->session_n, FILTER_SANITIZE_NUMBER_INT);
         }
         $session_sequence_index = $session_n;

         # Category 3 Data: Segmenting

         $segment = 0;
         if(isset($datum->level)) {
            $segment = filter_var($datum->level, FILTER_SANITIZE_NUMBER_INT);
         }

         # Category 4 Data: Provenance

         // Static, nothing to set here

         # Category 5 Data: Versioning

         $game_version_raw = null;
         if(isset($_REQUEST["app_version"])) $game_version_raw = filter_var($_REQUEST["app_version"],           FILTER_SANITIZE_NUMBER_INT); else die("No app_version");

         $schema_version = "0.0";

         $game_version = "1.0";
         $log_version  = $game_version_raw;
         
         # Category 6 Data: Configuration

         $condition  = "main";

         # Category 7 Data: Context

         $persistent_session_id = null;
         if(isset($_REQUEST["persistent_session_id"])) $persistent_session_id = filter_var($_REQUEST["persistent_session_id"], FILTER_SANITIZE_NUMBER_INT);
         $player_history = json_encode( ["persistent_session_id" => $persistent_session_id] );

         $game_state = "{}";

         # Category 8 Data: Event

         $event_id = 9999;
         if(isset($datum->event_custom)) {
            $event_custom = filter_var($datum->event_custom, FILTER_SANITIZE_NUMBER_INT);
            $event_id = 9000 + (int) $event_custom;
         }

         $event_name = "UNDEFINED";
         if(isset($datum->event)) {
            $event_name = $datum->event;
         }
         $event_name = $event_name.".".$event_custom;

         $event_data = $datum->event_data_complex ?? "{}";

         return new Event(
            game_id:$game_id,           instance_id:null,               player_id:$player_id,     session_id:$session_id,
            timestamp:$client_time,     game_time:$game_time,           sequence_index:$session_sequence_index,
            game_segment:$segment,
            game_version:$game_version, schema_version:$schema_version, log_version:$log_version,
            condition:$condition,       game_config:null,               platform:null,
            game_state:$game_state,     player_history:$player_history,
            event_id:$event_id,         event_name:$event_name,         event_data:$event_data,     
         );
      }

      function AsMySQLQuery($conn) : string
      {
         $event_data_str = !is_null($this->event_data)    ?      mysqli_real_escape_string($conn, $this->event_data)         : "NULL";
         return "(".
            "\"".mysqli_real_escape_string($conn, $this->session_id)."\",".
            "\"".mysqli_real_escape_string($conn, $this->player_id)."\",".
            "\"".mysqli_real_escape_string($conn, $this->player_history)."\",".
            "\"".mysqli_real_escape_string($conn, "placeholder client_time")."\",".
            "\"".mysqli_real_escape_string($conn, "placeholder client_time_ms")."\",".
            ""."placeholder offset".",".
            "".Event::$server_time.",".
            "\"".mysqli_real_escape_string($conn, $this->event_name)."\",".
            "\"".$event_data_str."\",".
            "\"".Event::$event_source."\",".
            "\"".mysqli_real_escape_string($conn, $this->game_state)."\",".
            "\"".mysqli_real_escape_string($conn, $this->game_version)."\",".
            "\"".mysqli_real_escape_string($conn, $this->condition)."\",".
            "\"".mysqli_real_escape_string($conn, $this->log_version)."\",".
            "\"".mysqli_real_escape_string($conn, $this->session_sequence_index)."\",".
            "\"".mysqli_real_escape_string($conn, Event::$host)."\",".
            "\"".mysqli_real_escape_string($conn, Event::$remote_addr)."\",".
            "\"".mysqli_real_escape_string($conn, Event::$http_user_agent)."\",".
            "\"".Event::$synced."\"".
         ")";
      }

      function AsBigQuery() : array
      {
         return [
            "session_id"           => $this->session_id,
            "user_id"              => $this->player_id,
            "user_data"            => $this->player_history,
            "client_time"          => $this->timestamp,
            // "client_time_ms" => $this->client_time_ms,
            "client_offset"        => "placeholder offset",
            "event_name"           => $this->event_name,
            "event_data"           => $this->event_data,
            "event_source"         => $this::$event_source,
            // "synced" => $this::synced,
            "game_state"           => $this->game_state,
            "app_version"          => $this->game_version,
            "app_branch"           => $this->condition,
            "log_version"          => $this->log_version,
            "event_sequence_index" => Event::$session_sequence_index,
            "host"                 => Event::$host,
            "remote_addr"          => Event::$remote_addr
         ];
      }
   }

?>
