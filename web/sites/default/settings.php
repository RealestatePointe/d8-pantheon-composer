<?php

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = __DIR__ . '/services.yml';

/**
 * Include the Pantheon-specific settings file.
 *
 * n.b. The settings.pantheon.php file makes some changes
 *      that affect all environments that this site
 *      exists in.  Always include this file, even in
 *      a local development environment, to ensure that
 *      the site settings remain consistent.
 */
include __DIR__ . "/settings.pantheon.php";

/**
 * Place the config directory outside of the Drupal root.
 */
$settings['config_sync_directory'] = dirname(DRUPAL_ROOT) . '/config/sync';

/**
 * Config split.
 */
if (
  (isset($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] != 'test' && $_ENV['PANTHEON_ENVIRONMENT'] != 'live')
) {
  $config['config_split.config_split.preproduction']['status'] = TRUE;
}
if (
  isset($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] == 'test'
) {
  $config['config_split.config_split.test']['status'] = TRUE;
}

/**
 * If there is a local settings file, then include it
 */
$local_settings = __DIR__ . "/settings.local.php";
if (file_exists($local_settings)) {
  include $local_settings;
}

/**
 * Always install the 'standard' profile to stop the installer from
 * modifying settings.php.
 */
$settings['install_profile'] = 'realestatepointe_standard';

/**
 * Avoid the trusted host settings warning
 * See https://pantheon.io/docs/settings-php/#trusted-host-setting
 * This is not being pre-configured for the live environment to avoid launching without the real hostname defined.
 * The Pantheon Lando recipe closely emulates the true platform enviornment, and by default sets PANTHEON_ENVIRONMENT
 * to "lando", so it needs to be excluded as well. I think all other cases would be valid: dev, test, and all multidev.
 */
if (isset($_ENV['PANTHEON_ENVIRONMENT'])) {
  if (!in_array($_ENV['PANTHEON_ENVIRONMENT'], array('lando', 'live'))) {
    $settings['trusted_host_patterns'][] = "{$_ENV['PANTHEON_ENVIRONMENT']}-{$_ENV['PANTHEON_SITE_NAME']}.getpantheon.io";
    $settings['trusted_host_patterns'][] = "{$_ENV['PANTHEON_ENVIRONMENT']}-{$_ENV['PANTHEON_SITE_NAME']}.pantheon.io";
    $settings['trusted_host_patterns'][] = "{$_ENV['PANTHEON_ENVIRONMENT']}-{$_ENV['PANTHEON_SITE_NAME']}.pantheonsite.io";
    $settings['trusted_host_patterns'][] = "{$_ENV['PANTHEON_ENVIRONMENT']}-{$_ENV['PANTHEON_SITE_NAME']}.panth.io";
  }
}

/**
 * Redirects
 * Default configuration redirects Pantheon platform domains to their HTTPS equivalents. 
 * See https://pantheon.io/docs/domains/#platform-domains
 * To avoid applying unintended redirects to real hostnames at launch, redirects are only applied in the live 
 * environment to platform domains. And pre-launch platform domains default to 302 status code.
 * @todo: At launch, it's important to customize the live environment as follows:
 *  - Set $primary_domain to a real hostname and remove the platform domain condition
 *  - Set $redirect_code to 301
 *. - Add conditions on specific paths from the old site (where relevant) and conditionally set the status code
 */
if (isset($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] != 'lando' && php_sapi_name() != 'cli') {
  $redirect_uri = $_SERVER['REQUEST_URI'];
  $redirect_code = 302;

  if ($_ENV['PANTHEON_ENVIRONMENT'] === 'live') {
    //$primary_domain = 'example.com';
    //$redirect_code = 301;
    // When launching with a real hostname, uncomment the previous two lines, set the hostname, and remove the following condition
    if (preg_match('/\.pantheonsite\.io$/', $_SERVER['HTTP_HOST'])) {
      $primary_domain = $_SERVER['HTTP_HOST'];
    }
    // Additional conditions for specific paths, e.g. at launch to redirect old URLs to their new locations
  } else {
    $primary_domain = $_SERVER['HTTP_HOST'];
  }

  if (isset($primary_domain)) {
    if ($_SERVER['HTTP_HOST'] != $primary_domain
        || $_SERVER['REQUEST_URI'] != $redirect_uri
        || !isset($_SERVER['HTTP_USER_AGENT_HTTPS'])
        || $_SERVER['HTTP_USER_AGENT_HTTPS'] != 'ON' ) {

      if (extension_loaded('newrelic')) {
        newrelic_name_transaction("redirect");
      }

      switch ($redirect_code) {
        case 301:
          header('HTTP/1.0 301 Moved Permanently');
          break;
        case 302:
          header('HTTP/1.0 302 Found');
          break;
        case 307:
          header('HTTP/1.0 307 Temporary Redirect');
          break;
      }
      header('Location: https://'. $primary_domain . $redirect_uri);
      exit();
    }
  }
}

/**
 * Email credentials
 * See https://pantheon.io/docs/email/#outgoing-email
 * See https://pantheon.io/docs/private-paths/
 * Avoid storing smtp credentials in config. A complete URL (parsable by parse_url) should be stored 
 * in secrets.json with the key smtp. The url scheme should be smtp, ssl, or tls for the desired
 * smtp encryption protocol.
 *   e.g. terminus secrets:set smtp ssl://username:password@hostname:port
 * @todo: Set an administrative message if this is not configured properly (i.e. if the secret is missing).
 *   In the absence of credentials, SMTP will be disabled and mail delivery will revert to using the
 *   the less reliable local MTA (postfix).
 */
if (isset($_ENV['PANTHEON_ENVIRONMENT'])) {
  $secrets_file = 'sites/default/files/private/secrets.json';
  if (file_exists($secrets_file)) {
    if ($secrets_text = file_get_contents($secrets_file)) {
      $secrets_json = json_decode($secrets_text, TRUE);
      if (isset($secrets_json['smtp'])) {
        $secret_parts = parse_url($secrets_json['smtp']);
        if (isset($secret_parts['host']) && isset($secret_parts['user']) && isset($secret_parts['pass'])) {
          $config['system.mail']['interface']['default'] = 'SMTPMailSystem';
          $config['smtp.settings']['smtp_on'] = TRUE;
          $config['smtp.settings']['smtp_host'] = $secret_parts['host'];
          $config['smtp.settings']['smtp_username'] = $secret_parts['user'];
          $config['smtp.settings']['smtp_password'] = $secret_parts['pass'];
          if (isset($secret_parts['port'])) {
            $config['smtp.settings']['smtp_port'] = $secret_parts['port'];
          }
          if (isset($secret_parts['scheme']) && ($secret_parts['scheme'] == 'ssl' || $secret_parts['scheme'] == 'tls')) {
            $config['smtp.settings']['smtp_protocol'] = $secret_parts['scheme'];
          }
        }
      }
    }
  }
}

/**
 * Reroute email
 *
 * Set destination dynamically to add an address extension indicating the site
 * and environment from which the email was redirected.
 */
if (isset($_ENV['PANTHEON_SITE_NAME']) && isset($_ENV['PANTHEON_ENVIRONMENT'])) {
  $config['reroute_email.settings']['address'] = 'devel+' . $_ENV['PANTHEON_SITE_NAME'] . '-' . $_ENV['PANTHEON_ENVIRONMENT'] . '@websites.realestatepointe.com';
}
