/**
 * sendTestResult
 * sending result of test to CWA Backend Test Result Server
 *
 * @param $url      - Endpoint URL - https://quicktest-result-cff4f7147260.coronawarn.app/api/v1/quicktest/results
 * @param $json     - {"testResults":[{"id": "'.$hash.'","result": '.$testResult.',"sc": '.$timestamp.'}]}
 * @param $certPath - Path to your certificate xxxxx.cer
 * @param $keyPath  - Path to your key file xxx-wru.key
 * @param $pass     - Your password for the key.
 *
 * @return array|bool|mixed
 * @author PocketMarkt.de
 */
public function sendTestResult($url, $json, $certPath, $keyPath, $pass)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_PORT, 443);
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
  curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
  curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $pass);
  curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

  curl_exec($ch);
  if (curl_errno($ch) > 0) {
    return array("curl_error_".curl_errno($ch) => curl_error($ch));
  }
  $info = curl_getinfo($ch);
  //echo '<pre>'.print_r($info, TRUE).'</pre>';
  curl_close($ch);
  if ($info['http_code'] == 204) return TRUE;
  return $info;
}
