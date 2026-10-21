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
      private string $game_id;

      function __construct($schema, $game_id, $data)
      {
         $n_rows = count($data);

         $this->game_id = $game_id;
         switch ($schema) {
            case LOGGER_SCHEMA:
               $lambda = fn($datum) => Event::FromLoggerLegacyFormat($game_id, $datum);
               break;
            case OGD_SCHEMA_01:
               $lambda = fn($datum) => Event::FromOGDLegacyFormat($game_id, $datum);
               break;
            case OGD_SCHEMA_10:
               $lambda = fn($datum) => Event::FromOGDStandardFormat($game_id, $datum);
               break;
            default:
               error_log("Got schema name ".$schema." that did not match ".LOGGER_SCHEMA." or ".OGD_SCHEMA_01." or ".OGD_SCHEMA_10.", defaulting to ".OGD_SCHEMA_10);
               $lambda = fn($datum) => Event::FromOGDStandardFormat($game_id, $datum);
               break;
         }
         $this->events = array_map($lambda, $data);
      }

      function AsBigQuery($conn) {
         $lambda = fn(Event $next_event) => [ 'data' => $next_event->AsBigQuery($conn) ];
         return array_map($lambda, $this->events);
      }

      function AsMySQL($conn) : string
      {
         $lambda = fn(Event $next_event) => $next_event->AsMySQLQuery($conn);
         $cols = "INSERT INTO ".$this->game_id." ".EventQuery::OGD_01_COLUMNS;
         $vals = join(",", array_map($lambda, $this->events));
         return $cols.$vals;
      }

   }

   class Event {

      # Category 0 Data: Non-logged data
      private static int $synced = 0;

      # Category 1 Data: Identification
      private        string $game_id;
      private        string $instance_id;
      private        string $player_id;
      private        string $session_id;
      # Category 2 Data: Sequencing
      private        DateTimeImmutable $timestamp;
      private       ?DateTimeImmutable $authoritative_timestamp;
      private       ?float $game_time;
      private        int $session_sequence_index; // TODO : this is listed as non-required in standard, that's probably wrong.
      # Category 3 Data: Segmenting
      private       ?string $game_segment;
      # Category 4 Data: Provenance
      private static string $event_source = "GAME";
      # Category 5 Data: Versioning
      private        string $source_version;
      private        string $game_version;
      private        string $schema_version;
      private        string $log_version;
      # Category 6 Data: Configuration
      private       ?string $condition;
      private       ?string $game_configuration;
      private       ?string $platform;
      # Category 7 Data: Context
      private       ?string $game_state;
      private       ?string $player_history;
      # Category 8 Data: Event
      private        string $event_id;
      private       ?string $event_name;
      private        string $event_data;
      # Category 9 Data: Private
      // Nothing here for now, in discussion for final standard

      function __construct(
         string $game_id,              ?string $instance_id,    ?string $player_id,    string $session_id,
         DateTimeImmutable $timestamp, ?DateTimeImmutable $auth_timestamp,
         ?float $game_time,            ?string $sequence_index, ?string $game_segment,
         string $game_version,         string $schema_version,  string $log_version,
         ?string $condition,           ?string $game_config,    ?string $platform,
         ?string $game_state,          ?string $player_history,
         ?int $event_id,               ?string $event_name,     string $event_data,
      )
      {
         $this->game_id     = $game_id;
         $this->session_id  = $session_id;
         $this->player_id   = $player_id ?? $this->session_id;
         $this->instance_id = $instance_id ?? $this->player_id;

         $this->timestamp               = $timestamp;
         $this->authoritative_timestamp = $auth_timestamp;
         $this->game_time               = $game_time;
         $this->session_sequence_index  = $sequence_index;

         $this->game_segment = $game_segment ?? "{}";

         $this->source_version = $game_version; // Game is always the source here.
         $this->game_version   = $game_version;
         $this->schema_version = $schema_version;
         $this->log_version    = $log_version;

         $this->condition          = $condition;
         $this->game_configuration = $game_config ?? "{}";
         $this->platform           = $platform    ?? "{}";

         $this->game_state     = $game_state     ?? "{}";
         $this->player_history = $player_history ?? "{}";

         $this->event_id   = $event_id   ?? 9999;
         $this->event_name = $event_name ?? Event::IDtoName($this->event_id);
         $this->event_data = $event_data;
      }

      static function IDtoName(int $event_id) : string {
         $ret_val = null;

         switch ($event_id) {
            default:
               $ret_val = "custom_event";
         }
         return $ret_val;
      }

      static function FromOGDStandardFormat($game_id, $datum) : Event
      /** Create an Event object from the standard OGD format (Schema v1.0)
       * 
       * Items from $_REQUEST:
       * - game_id (technically getting this as input)
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

         $raw = $_REQUEST["instance_id"];
         $instance_id = isset($raw)
                      ? preg_replace("/[^a-zA-Z0-9]+/", "", $raw)
                      : null;

         $raw = $_REQUEST["player_id"];
         $player_id   = isset($raw)
                      ? preg_replace("/[^a-zA-Z0-9]+/", "", $raw)
                      : null;

         $raw = $_REQUEST["session_id"];
         $session_id = isset($raw)
                     ? filter_var($raw, FILTER_SANITIZE_NUMBER_INT)
                     : die("No session_id found in the logger request.");

         # Category 2 Data: Sequencing
         $raw = $datum->timestamp;
         $timestamp = isset($raw)
                    ? DateTimeImmutable::createFromFormat('Y-m-d*H:i:s.uT', $raw)
                    : die("No timestamp found in the logger request.");
         if ($timestamp == false) {
            die("Invalid timestamp format, needs to match the format Y-m-d*H:i:s.uT");
         }

         $raw = $datum->authoritative_timestamp;
         $auth_timestamp = isset($raw)
                         ? DateTimeImmutable::createFromFormat('Y-m-d*H:i:s.uT', $raw) ?? null
                         : null;

         $raw = $datum->game_time;
         $game_time = isset($raw)
                    ? (float) filter_var($raw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
                    : null;

         $raw = $datum->session_sequence_index;
         $session_sequence_index = isset($raw)
                                 ? (int) filter_var($raw, FILTER_SANITIZE_NUMBER_INT)
                                 : null;

         # Category 3 Data: Segmenting

         $raw = $datum->game_segment;
         $segment = isset($raw)
                  ? ( json_validate($raw)
                    ? $raw
                    : die("Invalid JSON in game_segment! ".json_last_error()." : ".json_last_error_msg())
                  )
                  : null;

         # Category 4 Data: Provenance

         // Static, nothing to set here

         # Category 5 Data: Versioning

         // source_version is given the game_version

         $raw = $_REQUEST["source_version"];
         $source_version = isset($raw)
                       ? filter_var($raw, FILTER_SANITIZE_NUMBER_INT)
                       : ( isset($_REQUEST["game_version"])
                         ? filter_var($_REQUEST["game_version"], FILTER_SANITIZE_NUMBER_INT)
                         : die("No source_version found in the logger request.")
                       );
                       
         $raw = $_REQUEST["schema_version"];
         $schema_version = isset($raw)
                         ? $raw
                         : die("No schema_version found in the logger request.");

         $raw = $_REQUEST["log_version"];
         $log_version = isset($raw)
                      ? filter_var($raw, FILTER_SANITIZE_NUMBER_INT)
                      : die("No log_version found in the logger request.");

         # Category 6 Data: Configuration

         $raw = $_REQUEST["condition"];
         $condition = isset($raw)
                    ? preg_replace("/[^a-zA-Z0-9-_]+/", "", $raw)
                    : null;

         $raw = $_REQUEST["game_configuration"];
         $game_config = isset($raw)
                      ? ( json_validate($raw)
                        ? $raw
                        : die("Invalid JSON in game_configuration! ".json_last_error()." : ".json_last_error_msg())
                      )
                      : null;

         $raw = $_REQUEST["platform"];
         $platform = isset($raw)
                   ? ( json_validate($raw)
                     ? $raw
                     : die("Invalid JSON in platform! ".json_last_error()." : ".json_last_error_msg())
                   )
                   : null;

         # Category 7 Data: Context

         $raw = $datum->game_state;
         $game_state = isset($raw)
                     ? ( json_validate($raw)
                       ? $raw
                       : die("Invalid JSON in game_state! ".json_last_error()." : ".json_last_error_msg())
                     )
                     : null;

         $raw = $_REQUEST["player_history"];
         $player_history = isset($raw)
                         ? ( json_validate($raw)
                           ? $raw
                           : die("Invalid JSON in player_history! ".json_last_error()." : ".json_last_error_msg())
                         )
                         : null;

         # Category 8 Data: Event
         $raw = $datum->event_id;
         $event_id = isset($raw)
                   ? (int) filter_var($raw, FILTER_SANITIZE_NUMBER_INT)
                   : null;

         $raw = $datum->event_name;
         $event_name = isset($raw)
                      ? preg_replace("/[^a-zA-Z0-9-_]+/", "", $raw)
                      : null;

         $raw = $datum->event_data;
         $event_data = isset($raw)
                     ? ( json_validate($raw)
                       ? $raw
                       : die("Invalid JSON in event_data! ".json_last_error()." : ".json_last_error_msg())
                     ) 
                     : die("No event_data found in the logger request.");

         # Category 9 Data: Private

         return new Event(
            game_id:$game_id,                       instance_id:$instance_id,             player_id:$player_id,     session_id:$session_id,
            timestamp:$timestamp,                   auth_timestamp:$auth_timestamp,       game_time:$game_time,
            sequence_index:$session_sequence_index, game_segment:$segment,
            game_version:$source_version,           schema_version:$schema_version,       log_version:$log_version,
            condition:$condition,                   game_config:$game_config,             platform:$platform,
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
         # Category 1 Data: Identification

         // per dump
         $player_id = NULL;   
         $player_history = NULL;
         $event_data = NULL;
         $game_state = NULL;
         $condition = NULL;

         # Category 1 Data: Identification

         if(isset($_REQUEST["session_id"])) {
            $session_id = filter_var($_REQUEST["session_id"], FILTER_SANITIZE_NUMBER_INT);
         } else { die("No session_id"); }

         if(isset($_REQUEST["user_id"])) {
            $player_id = preg_replace("/[^a-zA-Z0-9]+/", "", $_REQUEST["user_id"]);
         }
         $player_id = $session_id;

         # Category 2 Data: Sequencing

         // $client_time = $datum->client_time ?? new DateTimeImmutable("Y-m-d H:i:s");
         $client_time = isset($datum->client_time)
                      ? DateTimeImmutable::createFromFormat('Y-m-d*H:i:s.u', $datum->client_time) ?? new DateTimeImmutable()
                      : new DateTimeImmutable();

         $offset_components = explode(":", $datum->client_offset ?? "+00:00");
         $prefix = (str_starts_with($offset_components[0], "+") || str_starts_with($offset_components[0], "-"))
                 ? ""
                 : "+";
         $offset_str = $prefix.$offset_components[0].":".$offset_components[1];
         $client_offset =  new DateTimeZone($offset_str);
         if (!$client_offset) {
            error_log("Could not create timezone from offset string of ".$offset_str);
         }
         else {
            $client_time = $client_time->setTimezone($client_offset) ?? $client_time;
         }

         $game_time = 0.0; // Don't have a great default here

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

         return new Event(
            game_id:$game_id,                       instance_id:$player_id,         player_id:$player_id,     session_id:$session_id,
            timestamp:$client_time,                 auth_timestamp:null,            game_time:$game_time,
            sequence_index:$session_sequence_index, game_segment:null,
            game_version:$game_version,             schema_version:$schema_version, log_version:$log_version,
            condition:$condition,                   game_config:null,               platform:null,
            game_state:$game_state,                 player_history:$player_history,
            event_id:$event_id,                     event_name:$event_name,         event_data:$event_data,     
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
         if(isset($_REQUEST["session_id"])) {
            $session_id = filter_var($_REQUEST["session_id"], FILTER_SANITIZE_NUMBER_INT);
         }
         else {
            die("No session_id");
         }
         $player_id = isset($_REQUEST["player_id"])
                    ? preg_replace("/[^a-zA-Z0-9]+/", "", $_REQUEST["player_id"])
                    : $session_id;

         # Category 2 Data: Sequencing

         $client_time = isset($datum->client_time)
                      ? DateTimeImmutable::createFromFormat('Y-m-d*H:i:s.u', $datum->client_time) ?? new DateTimeImmutable()
                      : new DateTimeImmutable();

         $game_time = 0.0; // Don't have a great default here

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
            game_id:$game_id,                       instance_id:$player_id,         player_id:$player_id,     session_id:$session_id,
            timestamp:$client_time,                 game_time:$game_time,           auth_timestamp:null,
            sequence_index:$session_sequence_index, game_segment:$segment,
            game_version:$game_version,             schema_version:$schema_version, log_version:$log_version,
            condition:$condition,                   game_config:null,               platform:null,
            game_state:$game_state,                 player_history:$player_history,
            event_id:$event_id,                     event_name:$event_name,         event_data:$event_data,     
         );
      }

      function AsMySQLQuery($conn) : string
      {
         throw new Exception("MySQL not supported!");
         $event_data_str = !is_null($this->event_data) ? mysqli_real_escape_string($conn, $this->event_data)         : "NULL";
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
            "\"".Event::$synced."\"".
         ")";
      }

      function AsBigQuery($conn) : array
      {
         return [
            "instance_id"             => $this->instance_id,
            "player_id"               => $this->player_id,
            "session_id"              => $this->session_id,
            "timestamp"               => $conn->timestamp($this->timestamp),
            "timezone_offset"         => $this->timestamp->format("P"), // get just the timezone offset
            "authoritative_timestamp" => $this->authoritative_timestamp ? $conn->timestamp($this->authoritative_timestamp) : null,
            "game_time"               => $this->game_time,
            "session_sequence_index"  => $this->session_sequence_index,
            "game_segment"            => $this->game_segment,
            "event_source"            => Event::$event_source,
            "source_version"          => $this->source_version,
            "game_version"            => $this->game_version,
            "schema_version"          => $this->schema_version,
            "log_version"             => $this->log_version,
            "condition"               => $this->condition,
            "game_configuration"      => $this->game_configuration,
            "platform"                => $this->platform,
            "game_state"              => $this->game_state,
            "player_history"          => $this->player_history,
            "event_id"                => $this->event_id,
            "event_name"              => $this->event_name,
            "event_data"              => $this->event_data,
         ];
      }
   }

?>
