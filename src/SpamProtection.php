<?php

namespace Drupal\vspam;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\RfcLogLevel;

/**
 * Provides a spam protection service.
 */
class SpamProtection implements SpamProtectionInterface {
  use LoggerChannelTrait;

  /**
   * The configuration factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache backend to use.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Spam protection settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Last result of reCAPTCHA request.
   *
   * @var array
   */
  protected $lastResult;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cache_backend) {
    $this->configFactory = $config_factory;
    $this->cacheBackend = $cache_backend;
    $this->settings = $this->configFactory->get('vspam.settings');
  }

  /**
   * Calls the reCAPTCHA siteverify API to verify whether the user passes CAPTCHA test for configured forms.
   *
   * @param string $response
   *   The value of 'g-recaptcha-response' in the submitted form.
   * @param string $form_id
   *   The form ID.
   *
   * @return bool
   *   If user passes CAPTCHA test.
   */
  public function verify(string $response, string $form_id) {
    $action = $this->settings->get('forms.' . $form_id . '.action') ?: $form_id;
    $score = $this->settings->get('forms.' . $form_id . '.score') ?: 0.5;

    return $this->verifyResponse($response, $action, $score);
  }

  /**
   * Calls the reCAPTCHA siteverify API to verify whether the user passes CAPTCHA test.
   *
   * @param string $response
   *   The value of 'g-recaptcha-response' in the submitted form.
   * @param string $action
   *   The reCAPTCHA action.
   * @param float $score
   *   The reCAPTCHA score.
   *
   * @return bool
   *   If user passes CAPTCHA test.
   */
  public function verifyResponse(string $response, string $action, float $score = 0.5) {
    if (!$response) {
      return FALSE;
    }

    // Check the reCAPTCHA response in Database
    $cid = 'vspam:'. hash('sha256', $response);

    if ($this->cacheBackend->get($cid)) {
      return FALSE;
    }

    $this->cacheBackend->set($cid, [], time() + 86400);

    // Send request to Google
    $params = ['response' => $response];
    $params['remoteip'] = \Drupal::request()->getClientIp();
    $result = $this->request($params);
    $result['ip'] = $params['remoteip'];
    $this->lastResult[$response] = $result;

    return isset($result['success']) && $result['success'] == TRUE
            && $result['action'] == $action && $result['score'] >= $score;
  }

  /**
   * Get last result of reCAPTCHA request.
   *
   * @param string $response
   *   The value of 'g-recaptcha-response' in the submitted form.
   *
   * @return array|null
   *   Last result of reCAPTCHA request.
   */
  public function getLastResult(string $response) {
    return isset($this->lastResult[$response]) ? $this->lastResult[$response] : NULL;
  }

  /**
   * Submits the cURL request with the specified parameters.
   *
   * @param array $params
   *   Request parameters
   *
   * @return array
   *   Decoded body of the reCAPTCHA response
   */
  protected function request(array $params) {
    try {
      $response = $this->getClient()->request('POST', '', [
        'form_params' => $params + ['secret' => $this->settings->get('secret_key')],
      ]);
      $result = Json::decode((string) $response->getBody());

      if (is_array($result)) {
        return $result;
      }
    }
    catch (GuzzleException $e) {
      $this->getLogger('vspam')->log(RfcLogLevel::ERROR, $e);
    }

    return NULL;
  }

  /**
   * Creates a http client.
   *
   * @return \GuzzleHttp\Client
   *   The http client.
   */
  protected function getClient() {
    return new Client([
      'base_uri' => self::GOOGLE_RECAPTCHA_API_URL,
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Accept' => 'application/json; charset=UTF-8',
        'Accept-Encoding' => 'gzip',
        'User-Agent' => 'Drupal '. \Drupal::VERSION .' (gzip)',
      ],
      'connect_timeout' => 3,
      'timeout' => 15,
    ]);
  }

}
