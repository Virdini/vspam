<?php

namespace Drupal\vspam;

/**
 * Provides an interface defining a spam protection service.
 */
interface SpamProtectionInterface {

  /**
   * We are allowed to hide the badge as long as we include the reCAPTCHA branding visibly in the user flow. Please include the following text.
   *
   * @link https://developers.google.com/recaptcha/docs/faq#id-like-to-hide-the-recaptcha-badge.-what-is-allowed
   */
  const GOOGLE_RECAPTCHA_BRANDING = 'This site is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy">Privacy Policy</a> and <a href="https://policies.google.com/terms">Terms of Service</a> apply.';

  /**
   * URL to which requests are sent via cURL.
   */
  const GOOGLE_RECAPTCHA_API_URL = 'https://www.google.com/recaptcha/api/siteverify';

}
