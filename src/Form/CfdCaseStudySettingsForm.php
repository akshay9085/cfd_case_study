<?php

/**
 * @file
 * Contains \Drupal\cfd_case_study\Form\CfdCaseStudySettingsForm.
 */

namespace Drupal\cfd_case_study\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class CfdCaseStudySettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cfd_case_study_settings_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $config = \Drupal::config('cfd_case_study.settings');
    $form['emails'] = [
        '#type' => 'textfield',
        '#title' => $this->t('(Bcc) Notification emails'),
        '#description' => $this->t('Specify email IDs for Bcc option of mail system, comma-separated.'),
        // '#size' => 50,
        '#maxlength' => 255,
        '#required' => TRUE,
        '#default_value' => $config->get('emails'),
      ];
  
      $form['cc_emails'] = [
        '#type' => 'textfield',
        '#title' => $this->t('(Cc) Notification emails'),
        '#description' => $this->t('Specify email IDs for Cc option of mail system, comma-separated.'),
        // '#size' => 50,
        '#maxlength' => 255,
        '#required' => TRUE,
        '#default_value' => $config->get('cc_emails'),
      ];
  
      $form['from_email'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Outgoing from email address'),
        '#description' => $this->t('Email address to display in the "From" field of all outgoing messages.'),
        // '#size' => 50,
        '#maxlength' => 255,
        '#required' => TRUE,
        '#default_value' => $config->get('from_email'),
      ];
  
      $form['extensions']['resource_upload'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Allowed file extensions for uploading resource files'),
        '#description' => $this->t('Comma-separated list WITHOUT SPACE of allowed file extensions.'),
        // '#size' => 50,
        '#maxlength' => 255,
        '#required' => TRUE,
        '#default_value' => $config->get('resource_upload_extensions'),
      ];
  
      $form['extensions']['abstract_upload'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Allowed file extensions for abstract'),
        '#description' => $this->t('Comma-separated list WITHOUT SPACE of allowed file extensions.'),
        // '#size' => 50,
        '#maxlength' => 255,
        '#required' => TRUE,
        '#default_value' => $config->get('abstract_upload_extensions'),
      ];
  
      $form['extensions']['case_study_upload'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Allowed extensions for project files'),
        '#description' => $this->t('Comma-separated list WITHOUT SPACE of allowed file extensions.'),
        // '#size' => 50,
        '#maxlength' => 255,
        '#required' => TRUE,
        '#default_value' => $config->get('case_study_upload_extensions'),
      ];
  
      $form['extensions']['list_of_available_projects_file'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Allowed file extensions for file uploaded for available projects list'),
        '#description' => $this->t('Comma-separated list WITHOUT SPACE of allowed file extensions.'),
        // '#size' => 50,
        '#maxlength' => 255,
        '#required' => TRUE,
        '#default_value' => $config->get('list_of_available_projects_file_extensions'),
      ];
  
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ];
  
    return $form;
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    return;
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    \Drupal::configFactory()->getEditable('cfd_case_study.settings')->set('emails', $form_state->getValue('emails'))->save();
    \Drupal::configFactory()->getEditable('cfd_case_study.settings')->set('cc_emails', $form_state->getValue('cc_emails'))->save();
    \Drupal::configFactory()->getEditable('cfd_case_study.settings')->set('from_email', $form_state->getValue('from_email'))->save();
    \Drupal::configFactory()->getEditable('cfd_case_study.settings')->set('resource_upload_extensions', $form_state->getValue('resource_upload'))->save();
    
    \Drupal::configFactory()->getEditable('cfd_case_study.settings')->set('case_study_upload_extensions', $form_state->getValue('case_study_upload'))->save();
    \Drupal::configFactory()->getEditable('cfd_case_study.settings')->set('list_of_available_projects_file_extensions', $form_state->getValue('list_of_available_projects_file'))->save();
    \Drupal::configFactory()->getEditable('cfd_case_study.settings')->set('abstract_upload_extensions', $form_state->getValue('abstract_upload'))->save();
   



  $this->messenger()->addStatus($this->t('Settings updated successfully.'));
}

}
?>
