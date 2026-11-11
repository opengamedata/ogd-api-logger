<?php declare(strict_types=1);
   require_once 'tests/models/TestRequest.php';

   use PHPUnit\Framework\TestCase;
   use Testing\TestRequest;

   final class LogLocalCase extends TestCase
   {
      public function setUp(): void {
         error_reporting(E_ALL);
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
               "authoritative_timestamp" => date('Y-m-d H:i:s.uT'),
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
               "authoritative_timestamp" => date('Y-m-d H:i:s.uT'),
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

         $request = new TestRequest($test_url, $url_params, $post_fields);
         $response = $request->Execute();
         $code = $request->GetInfo(CURLINFO_HTTP_CODE);

         echo "Raw rresult\n";
         var_dump($response);

         $this->assertSame(
            $code, 200,
            "Test Fail: Unexpected response code '".$code."' from call to ".$test_url."\nMessage: ".$response
         );
         $this->assertSame(
            $response, "SUCCESS: dummy insert into ".$game_id,
            "Test Fail: Unexpected result '".$response."' from call to ".$test_url
         );
      }
   }

?>