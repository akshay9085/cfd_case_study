<?php

/**
 * @file
 * Contains \Drupal\cfd_case_study\Form\CfdCaseStudyEditUploadAbstractCodeForm.
 */

namespace Drupal\cfd_case_study\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

class CfdCaseStudyEditUploadAbstractCodeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cfd_case_study_edit_upload_abstract_code_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes'] = ['enctype' => 'multipart/form-data'];

    $proposal_id = (int) (\Drupal::routeMatch()->getParameter('id') ?: \Drupal::request()->query->get('id'));
    $proposal_data = \Drupal::database()
      ->select('case_study_proposal', 'csp')
      ->fields('csp')
      ->condition('id', $proposal_id)
      ->execute()
      ->fetchObject();

    if (!$proposal_data) {
      $this->messenger()->addError($this->t('Invalid proposal selected. Please try again.'));
      $form_state->setRedirect('cfd_case_study.proposal_edit_file_all');
      return [];
    }

    $existing_uploaded_a_file = $this->defaultValueForUploadedFiles('A', (int) $proposal_data->id) ?: new \stdClass();
    $existing_uploaded_a_file->filename = $existing_uploaded_a_file->filename ?? 'No file uploaded';

    $existing_uploaded_s_file = $this->defaultValueForUploadedFiles('S', (int) $proposal_data->id) ?: new \stdClass();
    $existing_uploaded_s_file->filename = $existing_uploaded_s_file->filename ?? 'No file uploaded';

    $form['project_title'] = [
      '#type' => 'item',
      '#markup' => $proposal_data->project_title,
      '#title' => $this->t('Title of the Case Study Project'),
    ];
    $form['contributor_name'] = [
      '#type' => 'item',
      '#markup' => $proposal_data->contributor_name,
      '#title' => $this->t('Contributor Name'),
    ];
    $form['upload_case_study_abstract'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload the Case Study abstract'),
      '#description' => $this->t('<span style="color:red;">Current File:</span> @file<br />Separate filenames with underscore. No spaces or any special characters allowed in filename.', [
        '@file' => $existing_uploaded_a_file->filename,
      ]) . '<br />' . $this->t('<span style="color:red;">Allowed file extensions: @ext</span>', [
        '@ext' => $this->getAllowedExtensions('A') ?: $this->t('Not configured'),
      ]),
    ];
    $form['upload_case_study_developed_process'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload the Case Directory'),
      '#description' => $this->t('<span style="color:red;">Current File:</span> @file<br />Separate filenames with underscore. No spaces or any special characters allowed in filename.', [
        '@file' => $existing_uploaded_s_file->filename,
      ]) . '<br />' . $this->t('<span style="color:red;">Allowed file extensions: @ext</span>', [
        '@ext' => $this->getAllowedExtensions('S') ?: $this->t('Not configured'),
      ]),
    ];
    $form['prop_id'] = [
      '#type' => 'hidden',
      '#value' => $proposal_data->id,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    $form['cancel'] = [
      '#type' => 'item',
      '#markup' => Link::fromTextAndUrl(
        $this->t('Cancel'),
        Url::fromRoute('cfd_case_study.proposal_edit_file_all')
      )->toString(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $files = \Drupal::request()->files->get('files') ?? [];
    $abstract = $files['upload_case_study_abstract'] ?? NULL;
    $process = $files['upload_case_study_developed_process'] ?? NULL;

    if ((!$abstract || !$abstract->isValid()) && (!$process || !$process->isValid())) {
      $form_state->setErrorByName('upload_case_study_abstract', $this->t('No files uploaded.'));
      return;
    }

    foreach ([
      'upload_case_study_abstract' => 'A',
      'upload_case_study_developed_process' => 'S',
    ] as $name => $file_type) {
      $upload = $files[$name] ?? NULL;
      if (!$upload || !$upload->isValid()) {
        continue;
      }

      $allowed_extensions_str = $this->getAllowedExtensions($file_type);
      $allowed_extensions = array_filter(array_map('strtolower', array_map('trim', explode(',', (string) $allowed_extensions_str))));
      $original_name = (string) $upload->getClientOriginalName();
      $temp_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

      if (!empty($allowed_extensions) && !in_array($temp_extension, $allowed_extensions, TRUE)) {
        $form_state->setErrorByName($name, $this->t('Only file with @ext extensions can be uploaded.', ['@ext' => $allowed_extensions_str]));
      }
      if ($upload->getSize() <= 0) {
        $form_state->setErrorByName($name, $this->t('File size cannot be zero.'));
      }
      if (!cfd_case_study_check_valid_filename($original_name)) {
        $form_state->setErrorByName($name, $this->t('Invalid file name specified. Only alphabets and numbers are allowed as a valid filename.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $proposal_id = (int) $form_state->getValue('prop_id');
    $root_path = cfd_case_study_path();
    $proposal_data = \Drupal::database()
      ->select('case_study_proposal', 'csp')
      ->fields('csp')
      ->condition('id', $proposal_id)
      ->execute()
      ->fetchObject();

    if (!$proposal_data) {
      $form_state->setRedirectUrl(Url::fromRoute('<front>'));
      return;
    }

    $submitted_abstract = \Drupal::database()
      ->select('case_study_submitted_abstracts', 'cssa')
      ->fields('cssa', ['id'])
      ->condition('proposal_id', $proposal_id)
      ->execute()
      ->fetchObject();

    if ($submitted_abstract) {
      $submitted_abstract_id = (int) $submitted_abstract->id;
    }
    else {
      $submitted_abstract_id = \Drupal::database()
        ->insert('case_study_submitted_abstracts')
        ->fields([
          'proposal_id' => $proposal_id,
          'approver_uid' => 0,
          'abstract_approval_status' => 0,
          'abstract_upload_date' => time(),
          'abstract_approval_date' => 0,
          'is_submitted' => 1,
        ])
        ->execute();

      \Drupal::database()
        ->update('case_study_proposal')
        ->fields(['is_submitted' => 1])
        ->condition('id', $proposal_id)
        ->execute();
    }

    $files = \Drupal::request()->files->get('files') ?? [];
    $file_system = \Drupal::service('file_system');
    $target_dir = rtrim($root_path . $proposal_data->directory_name, '/\\') . DIRECTORY_SEPARATOR;
    $prepared = $file_system->prepareDirectory($target_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    if (!$prepared) {
      $this->messenger()->addError($this->t('Unable to prepare the upload directory.'));
      return;
    }

    $abs_file_name = 'Not updated';
    $proj_file_name = 'Not updated';

    foreach ($files as $file_form_name => $upload) {
      if (!$upload || !$upload->isValid()) {
        continue;
      }

      $file_type = NULL;
      if (str_contains($file_form_name, 'upload_case_study_abstract')) {
        $file_type = 'A';
        $abs_file_name = $upload->getClientOriginalName();
      }
      elseif (str_contains($file_form_name, 'upload_case_study_developed_process')) {
        $file_type = 'S';
        $proj_file_name = $upload->getClientOriginalName();
      }

      if (!$file_type) {
        continue;
      }

      $original_name = $file_system->basename($upload->getClientOriginalName());
      $target_path = $target_dir . $original_name;
      $existing_file = \Drupal::database()
        ->select('case_study_submitted_abstracts_file', 'cssaf')
        ->fields('cssaf')
        ->condition('proposal_id', $proposal_id)
        ->condition('filetype', $file_type)
        ->execute()
        ->fetchObject();

      if ($existing_file && !empty($existing_file->filename)) {
        $old_path = $target_dir . $existing_file->filename;
        if (is_file($old_path)) {
          unlink($old_path);
        }
      }
      elseif (is_file($target_path)) {
        unlink($target_path);
      }

      try {
        $upload->move($target_dir, $original_name);
      }
      catch (\Exception $exception) {
        $this->messenger()->addError($this->t('@filename file was not updated successfully.', ['@filename' => $original_name]));
        continue;
      }

      $filemime = \Drupal::service('file.mime_type.guesser')->guessMimeType($target_path) ?: $upload->getClientMimeType();
      $filesize = file_exists($target_path) ? filesize($target_path) : 0;

      if ($existing_file) {
        \Drupal::database()
          ->update('case_study_submitted_abstracts_file')
          ->fields([
            'filename' => $original_name,
            'filepath' => $original_name,
            'filemime' => $filemime,
            'filesize' => $filesize,
            'timestamp' => time(),
          ])
          ->condition('proposal_id', $proposal_id)
          ->condition('filetype', $file_type)
          ->execute();
      }
      else {
        \Drupal::database()
          ->insert('case_study_submitted_abstracts_file')
          ->fields([
            'submitted_abstract_id' => $submitted_abstract_id,
            'proposal_id' => $proposal_id,
            'uid' => $user->id(),
            'approvar_uid' => 0,
            'filename' => $original_name,
            'filepath' => $original_name,
            'filemime' => $filemime,
            'filesize' => $filesize,
            'filetype' => $file_type,
            'timestamp' => time(),
          ])
          ->execute();
      }

      $this->messenger()->addStatus($this->t('@filename file updated successfully.', ['@filename' => $original_name]));
    }

    $config = \Drupal::config('cfd_case_study.settings');
    $from = $config->get('case_study_from_email') ?: $config->get('from_email') ?: \Drupal::config('system.site')->get('mail');
    if (empty($from)) {
      $from = 'no-reply@localhost';
    }
    $bcc = $config->get('case_study_emails') ?: $config->get('emails');
    $cc = $config->get('case_study_cc_emails') ?: $config->get('cc_emails');

    $email_to = $user->getEmail();
    $params['abstract_edit_file_uploaded']['proposal_id'] = $proposal_id;
    $params['abstract_edit_file_uploaded']['user_id'] = $user->id();
    $params['abstract_edit_file_uploaded']['abs_file'] = $abs_file_name;
    $params['abstract_edit_file_uploaded']['proj_file'] = $proj_file_name;
    $headers = [
      'From' => $from,
      'MIME-Version' => '1.0',
      'Content-Type' => 'text/plain; charset=UTF-8; format=flowed; delsp=yes',
      'Content-Transfer-Encoding' => '8Bit',
      'X-Mailer' => 'Drupal',
    ];
    if (!empty($cc)) {
      $headers['Cc'] = $cc;
    }
    if (!empty($bcc)) {
      $headers['Bcc'] = $bcc;
    }
    $params['abstract_edit_file_uploaded']['headers'] = $headers;
    $langcode = $user->getPreferredLangcode() ?: \Drupal::languageManager()->getDefaultLanguage()->getId();
    if ($email_to) {
      $result = \Drupal::service('plugin.manager.mail')->mail('cfd_case_study', 'abstract_edit_file_uploaded', $email_to, $langcode, $params, $from, TRUE);
      if (empty($result['result'])) {
        $this->messenger()->addError($this->t('Error sending email message.'));
      }
    }

    Cache::invalidateTags([
      'case_study_proposal_list',
      "case_study_proposal:$proposal_id",
    ]);

    $form_state->setRedirect('cfd_case_study.edit_upload_abstract_code_form', [], [
      'query' => ['id' => $proposal_id],
    ]);
  }

  /**
   * Returns the configured extension list for a file type.
   */
  protected function getAllowedExtensions(string $file_type): string {
    $config = \Drupal::config('cfd_case_study.settings');

    if ($file_type === 'A') {
      return (string) $config->get('resource_upload_extensions');
    }

    return (string) ($config->get('case_study_project_files_extensions') ?: $config->get('case_study_upload_extensions') ?: '');
  }

  /**
   * Returns the existing uploaded file row for a proposal/file type.
   */
  protected function defaultValueForUploadedFiles(string $filetype, int $proposal_id) {
    $query = \Drupal::database()->select('case_study_submitted_abstracts_file', 'cssaf');
    $query->fields('cssaf');
    $query->condition('proposal_id', $proposal_id);

    if ($filetype === 'S' || $filetype === 'A') {
      $query->condition('filetype', $filetype);
      return $query->execute()->fetchObject();
    }

    return NULL;
  }

}
