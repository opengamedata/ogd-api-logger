<?php declare(strict_types=1);
   require_once 'tests/models/TestRequest.php';

   use PHPUnit\Framework\TestCase;
   use Testing\TestRequest;

   final class LogRemoteCase extends TestCase
   {

      /* TODO : check into whether we should be inserting escape characters,
                as in what we had in old Python-based tests:
                ```python
                "game_state" : json.dumps({
                   "level" : 1
                }).replace('"', '\\\\"'),
                ```
      */

      public function testOGDLegacyCall(): void
      {
         $game_id = "TEST_GAME";
         $url_params = [
            "app_id" => $game_id,
            "session_id" => time(), // use a fake number that is always different so we don't get errors for duplicate event_sequence_index
            "user_id" => "TestUser",
            "user_data" => "{}",
            "app_version" => "1",
            "app_branch" => $_ENV['branch']."-testbed",
            "log_version" => "1"
         ];
         $body_params = [
            [
               "client_time" => "2020-01-01 01:23:45.678Z", //date('Y-m-d H:i:s'),
               "client_offset" => "00:00",
               "event_name" => "legacy_ogd_call_event",
               "event_data" => json_encode(["some_element" => "some_value", "some_int" => 0]),
               "game_state" => json_encode(["state_var" => "active"]),
               "event_sequence_index" => 1
            ],
            [
               "client_time" => "2020-01-01 12:34:56.789Z", //date('Y-m-d H:i:s'),
               "client_offset" => "01:00",
               "event_name" => "another_ogd_legacy_event",
               "event_data" => json_encode(["some_element" => "new_value", "some_int" => 42]),
               "game_state" => json_encode(["state_var" => "inactive"]),
               "event_sequence_index" => 2
            ]
         ];
         $post_fields = ["data" => base64_encode(json_encode($body_params))];
         $test_url = $_ENV['base_url'];

         $request = new TestRequest($test_url, "log.php", $url_params, $post_fields);
         $response = $request->Execute();
         $code = $request->GetInfo(CURLINFO_HTTP_CODE);

         $this->assertSame(
            $code, 200,
            "Test Fail: Unexpected response code '".$code."' from call to ".$test_url."\nMessage: ".$response
         );
         $this->assertSame(
            $response, "SUCCESS: Inserted 2 rows to BigQuery in ".$game_id.".".$game_id."_daily_".(new DateTime())->format("Ymd").".",
            "Test Fail: Unexpected result '".$response."' from call to ".$test_url
         );
      }

      public function testOGDLegacy_OddOffsets(): void
      {
         $game_id = "TEST_GAME";
         $url_params = [
            "app_id" => $game_id,
            "session_id" => time(), // use a fake number that is always different so we don't get errors for duplicate event_sequence_index
            "user_id" => "TestUser",
            "user_data" => "{}",
            "app_version" => "1",
            "app_branch" => $_ENV['branch']."-testbed",
            "log_version" => "1"
         ];
         $body_params = [
            [
               "client_time" => "2020-01-01 01:23:45.678Z", //date('Y-m-d H:i:s'),
               "client_offset" => "03:00:00",
               "event_name" => "ogd_legacy_odd_offset_event",
               "event_data" => json_encode(["some_element" => "some_value", "some_int" => 0]),
               "game_state" => json_encode(["state_var" => "active"]),
               "event_sequence_index" => 1
            ],
            [
               "client_time" => "2020-01-01 12:34:56.789Z", //date('Y-m-d H:i:s'),
               "client_offset" => "-04:00:00",
               "event_name" => "another_ogd_legacy_odd_offset_event",
               "event_data" => json_encode(["some_element" => "new_value", "some_int" => 42]),
               "game_state" => json_encode(["state_var" => "inactive"]),
               "event_sequence_index" => 2
            ]
         ];
         $post_fields = ["data" => base64_encode(json_encode($body_params))];
         $test_url = $_ENV['base_url'];

         $request = new TestRequest($test_url, "log.php", $url_params, $post_fields);
         $response = $request->Execute();
         $code = $request->GetInfo(CURLINFO_HTTP_CODE);

         $this->assertSame(
            $code, 200,
            "Test Fail: Unexpected response code '".$code."' from call to ".$test_url."\nMessage: ".$response
         );
         $this->assertSame(
            $response, "SUCCESS: Inserted 2 rows to BigQuery in ".$game_id.".".$game_id."_daily_".(new DateTime())->format("Ymd").".",
            "Test Fail: Unexpected result '".$response."' from call to ".$test_url
         );
      }

      public function testOGDStandardCall(): void
      {
         $game_id = "TEST_GAME";
         $url_params = [
            "game_id" => $game_id,
            "schema_version" => "1.0-alpha",
         ];
         $payload = [
            [
               "timestamp" => "2020-01-01 01:23:45.678Z", // date('Y-m-d\TH:i:s.uT'),
               "authoritative_timestamp" => date('Y-m-d\TH:i:s.uT'),
               "game_time" => "00:15:49.345",
               "session_sequence_index" => 1,
               "game_segment" => json_encode(["level" => 1]),
               "game_state" => json_encode(["state_var" => "active"]),
               "event_id" => 9999,
               "event_name" => "ogd_standard_event",
               "event_data" => json_encode(["some_element" => "some_value", "some_int" => 0])
            ],
            [
               "timestamp" => "2020-01-01 12:34:56.789+01:00", // date('Y-m-d\TH:i:s.uT'),
               "authoritative_timestamp" => date('Y-m-d\TH:i:s.uT'),
               "game_time" => "00:15:49.345",
               "session_sequence_index" => 2,
               "game_segment" => json_encode(["level" => 1]),
               "game_state" => json_encode(["state_var" => "inactive"]),
               "event_id" => 9999,
               "event_name" => "another_ogd_standard_event",
               "event_data" => json_encode(["some_element" => "new_value", "some_int" => 42])
            ]
         ];
         $post_fields = [
            "instance_id" => "fake_instance",
            "player_id" => "TestUser",
            "session_id" => time(), // use a fake number that is always different so we don't get errors for duplicate event_sequence_index
            "game_version" => "1.0.0-testbed",
            "log_version" => "1",
            "condition" => $_ENV['branch']."-testbed",
            "game_configuration" => "{}",
            "platform" => "{}",
            "player_history" => "{}",
            "data" => base64_encode(json_encode($payload))
         ];
         $test_url = $_ENV['base_url'];

         $request = new TestRequest($test_url, "log.php", $url_params, $post_fields);
         $response = $request->Execute();
         $code = $request->GetInfo(CURLINFO_HTTP_CODE);

         $this->assertSame(
            $code, 200,
            "Test Fail: Unexpected response code '".$code."' from call to ".$test_url."\nMessage: ".$response
         );
         $this->assertSame(
            $response, "SUCCESS: Inserted 2 rows to BigQuery in ".$game_id.".".$game_id."_daily_".(new DateTime())->format("Ymd").".",
            "Test Fail: Unexpected result '".$response."' from call to ".$test_url
         );
      }
   }

?>