<?php
use Google\Cloud\BigQuery\BigQueryClient;

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

   static function Connect() : BigQueryClient {
      try {
         $conn = new BigQueryClient();
         $test_datasets = $conn->datasets(["resultLimit" => 1]);
         if ($test_datasets->current()) {
            error_log("Made connection to BQ project, containing dataset ".$test_datasets->current()->id()."\n");
         }
         else {
            http_response_code(500);
            die("Could not find any datasets when connecting to event database.");
         }
      }
      catch (Exception $e) {
         http_response_code(500);
         error_log("Exception when trying to connect to BigQuery: ".$e->getMessage());
         die("Could not connect to event database");
      }
      return $conn;
   }

   static function Insert($conn, string $game_id, array $query) : string {
      try {
         $dataset = $conn->dataset(strtolower($game_id));
         $table_name = "{$dataset->id()}_daily_".date("Ymd");
         $table = $dataset->table($table_name);
         $opts = [
            "autoCreate" => true,
            "tableMetadata" => BigQueryUtils::STANDARD_METADATA_0_1
         ];
         $result = $table->insertRows($query, $opts);
         if (!$result->isSuccessful()) {
            $lambda = function($err) { return json_encode($err['errors']); };
            $msg = "Query for ".$game_id." failed with errors: ".join("\n", array_map($lambda, $result->failedRows()));
            http_response_code(500);
            error_log($msg);
            die("FAIL: ".$msg);
         }
      }
      catch(Exception $e) {
         $sql_err = "Query for ".$game_id." failed with error: ".$e->getMessage();
         error_log($sql_err);
         http_response_code(500);
         die("FAIL: ".$sql_err);
      }
      return "Inserted ".count($query)." rows to BigQuery in ".$dataset->id().".".$table_name.".";
   }
}

?>
