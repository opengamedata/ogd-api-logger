<?php declare(strict_types=1);
namespace Testing;

class TestRequest {
   static array $headers = [
      "Accept" => "*/*",
      "Accept-Language" => "en-US,en;q=0.5",
      "Accept-Encoding" => "gzip, deflate, br, zstd",
      "Content-Type" => "application/x-www-form-urlencoded",
      "DNT" => "1",
      "Connection" => "keep-alive",
      "Sec-Fetch-Dest" => "empty",
      "Sec-Fetch-Mode" => "cors",
      "Sec-Fetch-Site" => "cross-site"
   ];

   private $request;

   function __construct(string $base_url, array $url_params, array $post_params) {
      $request = curl_init();

      $base_url = $base_url ?: $_ENV['base_url'];
      $url = $base_url."/log.php?".http_build_query($url_params);

      $opts = [
         CURLOPT_URL => $url,
         CURLOPT_USERAGENT => "fake agent/1.0",
         CURLOPT_HTTPHEADER => TestRequest::$headers,
         CURLOPT_POST => true,
         CURLOPT_POSTFIELDS => $post_params,
         CURLOPT_POSTREDIR => CURL_REDIR_POST_302,
         CURLOPT_FOLLOWLOCATION => true,
         CURLOPT_RETURNTRANSFER => true
      ];
      curl_setopt_array($request, $opts);
   }

   function Execute(): string | bool {
      return curl_exec($this->request);
   }

   function GetInfo(?int $option): mixed {
      return curl_getinfo($this->request, $option);
   }
}

?>