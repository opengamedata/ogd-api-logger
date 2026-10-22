<?php

   namespace Logger\query;

   require_once 'event.php';
   use Logger\event\Event as Event;

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

?>
