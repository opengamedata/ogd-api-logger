<?php

class BigQueryUtils {
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

   static function Insert($conn, string $app_id, array $query) : string {
      // $dataset = $conn->dataset(strtolower($app_id));
      $dataset = $conn->dataset("migration_test");
      $table_name = "{$dataset->id()}_daily_".date("Ymd");
      $table = $dataset->table($table_name);
      $result = $table->insertRows($query, autoCreate:true, tableMetadata:BigQueryUtils::STANDARD_METADATA_0_1);
      if (!$result->isSuccessful()) {
         $lambda = function($err) { return $err['reason'].": ".$err['message']; };
         $msg = "Query for ".$app_id." failed with errors: ".join("\n", array_map($lambda, $result->failedRows()));
         error_log($msg);
         die("FAIL: ".$msg);
      }
      return "Inserted ".count($query)." rows.";
   }
}

?>
