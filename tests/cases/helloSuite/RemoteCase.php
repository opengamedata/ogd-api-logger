<?php declare(strict_types=1);
   use PHPUnit\Framework\TestCase;

   final class HelloRemoteCase extends TestCase
   {
      public function testBasicCall(): void
      {

         $request = curl_init();
         $test_url = $_ENV['base_url']."/hello.php";
         $opts = [
            CURLOPT_URL => $test_url,
            CURLOPT_POST => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true
         ];
         curl_setopt_array($request, $opts);

         $response = curl_exec($request);
         $code = curl_getinfo($request, CURLINFO_HTTP_CODE);

         $this->assertSame(
            $code, 200,
            "Test Fail: Unexpected response code '".$code."' from call to ".$test_url
         );
         $this->assertSame(
            $response, "Hello, world!",
            "Test Fail: Unexpected result '".$response."' from call to ".$test_url
         );
      }
   }

?>