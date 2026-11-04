<?php
namespace Logger;

use Exception;
use Google\Cloud\BigQuery\BigQueryClient;

class EchoOutput {

   static function Connect() : bool {
      return true;
   }

   static function Insert(string $game_id, array $query) : string {
      try {
         $dataset = strtoupper($game_id);
         $lines = array_merge(
            ["Dummy insert into ".$dataset],
            array_map(function($key) use ($query) { return $key.": ".$query[$key]; }, array_keys($query))
         );
         die(join("\n", $lines));
      }
      catch(Exception $e) {
         $sql_err = "Query for ".$game_id." failed with error";
         error_log($sql_err.": ".$e->getMessage());
         http_response_code(500);
         die("FAIL: ".$sql_err);
      }
   }
}

?>
