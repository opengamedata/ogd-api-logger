<?php

class BigQueryUtils {
   const STANDARD_METADATA_1_0 = [
      "schema" => [
         "fields" => [
            [ "name" => "instance_id",             "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "player_id",               "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "session_id",              "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "timestamp",               "type" => "TIMESTAMP", "mode" => "REQUIRED" ],
            [ "name" => "timezone_offset",         "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "authoritative_timestamp", "type" => "TIMESTAMP", "mode" => "NULLABLE" ],
            [ "name" => "game_time",               "type" => "FLOAT",     "mode" => "NULLABLE" ],
            [ "name" => "session_sequence_index",  "type" => "INTEGER",   "mode" => "REQUIRED" ],
            [ "name" => "game_segment",            "type" => "JSON",      "mode" => "REQUIRED" ],
            [ "name" => "event_source",            "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "source_version",          "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "game_version",            "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "schema_version",          "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "log_version",             "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "condition",               "type" => "STRING",    "mode" => "NULLABLE" ],
            [ "name" => "game_configuration",      "type" => "JSON",      "mode" => "REQUIRED" ],
            [ "name" => "platform",                "type" => "JSON",      "mode" => "REQUIRED" ],
            [ "name" => "game_state",              "type" => "JSON",      "mode" => "REQUIRED" ],
            [ "name" => "user_history",            "type" => "JSON",      "mode" => "REQUIRED" ],
            [ "name" => "event_id",                "type" => "INTEGER",   "mode" => "REQUIRED" ],
            [ "name" => "event_name",              "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "event_data",              "type" => "JSON",      "mode" => "REQUIRED" ],
         ]
      ]
   ];
   const STANDARD_METADATA_0_1 = [
      "schema" => [
         "fields" => [
            [ "name" => "session_id",           "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "user_id",              "type" => "STRING",    "mode" => "NULLABLE" ],
            [ "name" => "user_data",            "type" => "JSON",      "mode" => "NULLABLE" ],
            [ "name" => "client_time",          "type" => "TIMESTAMP", "mode" => "NULLABLE" ],
            [ "name" => "client_offset",        "type" => "INTEGER",   "mode" => "NULLABLE" ],
            [ "name" => "server_time",          "type" => "TIMESTAMP", "mode" => "REQUIRED" ],
            [ "name" => "event_name",           "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "event_data",           "type" => "JSON",      "mode" => "REQUIRED" ],
            [ "name" => "event_source",         "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "game_state",           "type" => "JSON",      "mode" => "NULLABLE" ],
            [ "name" => "app_version",          "type" => "INTEGER",   "mode" => "REQUIRED" ],
            [ "name" => "app_branch",           "type" => "STRING",    "mode" => "NULLABLE" ],
            [ "name" => "log_version",          "type" => "INTEGER",   "mode" => "REQUIRED" ],
            [ "name" => "event_sequence_index", "type" => "INTEGER",   "mode" => "REQUIRED" ],
            [ "name" => "remote_addr",          "type" => "STRING",    "mode" => "REQUIRED" ],
            [ "name" => "http_user_agent",      "type" => "STRING",    "mode" => "NULLABLE" ]
         ]
      ]
   ];

   static function Insert($conn, string $game_id, array $query) : string {
      $dataset = $conn->dataset(strtoupper($game_id));
      $table_name = "{$dataset->id()}_daily_".date("Ymd");
      $table = $dataset->table($table_name);
      $opts = [
         "autoCreate" => true,
         "tableMetadata" => BigQueryUtils::STANDARD_METADATA_1_0
      ];
      $result = $table->insertRows($query, $opts);
      if (!$result->isSuccessful()) {
         $lambda = function($err) { return json_encode($err['errors']); };
         $msg = "Query for ".$game_id." failed with errors: ".join("\n", array_map($lambda, $result->failedRows()));
         error_log($msg);
         die("FAIL: ".$msg);
      }
      return "Inserted ".count($query)." rows to BigQuery in ".$dataset->id().".".$table_name.".";
   }
}

?>
