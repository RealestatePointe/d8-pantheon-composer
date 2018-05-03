<?php

/**
 * @file
 * Drush runtime config for this Drupal instance.
 */

/**
 * If there is a local settings file, then include it
 */
$local_settings = __DIR__ . "/drushrc.local.php";
if (file_exists($local_settings)) {
  include $local_settings;
}
