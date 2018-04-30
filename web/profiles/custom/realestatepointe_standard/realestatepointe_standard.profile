<?php

/**
 * @file
 * Enables modules and site configuration for the RealestatePointe Standard profile.
 */

use Drupal\contact\Entity\ContactForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter() for install_configure_form().
 *
 * Allows the profile to alter the site configuration form. Copied from core standard profile.
 */
function realestatepointe_standard_form_install_configure_form_alter(&$form, FormStateInterface $form_state) {
  $form['#submit'][] = 'realestatepointe_standard_form_install_configure_submit';
}

/**
 * Submission handler to sync the contact.form.feedback recipient. Copied from core standard profile.
 */
function realestatepointe_standard_form_install_configure_submit($form, FormStateInterface $form_state) {
  $site_mail = $form_state->getValue('site_mail');
  ContactForm::load('feedback')->setRecipients([$site_mail])->trustData()->save();
}
