<?php

/**
 * @file
 * Contains \Drupal\cfd_case_study\Form\AddProjectTitleForm.
 */

namespace Drupal\cfd_case_study\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Database\Database;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\user\Entity\User;

class AddProjectTitleForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_project_title_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    /************************ start approve book details ************************/
    if ($user->id() == 0) {
      $this->messenger()->addError($this->t('It is mandatory to @login_link on this website to access the case study proposal form. If you are a new user, please create a new account first.', [
        '@login_link' => Link::fromTextAndUrl($this->t('login'), Url::fromRoute('user.page'))->toString(),
      ]));
      $form_state->setRedirect('user.login', [], ['query' => \Drupal::destination()->getAsArray()]);
      return [];
    }
    //  if ($user->uid == 0) {
    //   $msg = \Drupal::messenger()->addError(t('It is mandatory to ' . \Drupal\Core\Link::fromTextAndUrl('login', \Drupal\Core\Url::fromRoute('user.page')) . ' on this website to access the case study proposal form. If you are new user please create a new account first.'));
    //   drupal_goto('user');
    //   return $msg;
    // } //$user->uid == 0
    $form['#attributes'] = [
      'enctype' => "multipart/form-data"
      ];
    $form['new_project_title_name'] = [
      '#type' => 'textfield',
      '#title' => t('Enter the name of the project title'),
      // '#size' => 250,
      '#attributes' => [
        'placeholder' => t('Enter the name of the project title displayed to the contributor')
        ],
      '#maxlength' => 250,
      '#required' => TRUE,
    ];
    $form['upload_project_title_resource_file'] = [
      '#type' => 'fieldset',
      '#title' => t('Browse and upload the file to display with the project title'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];
    // @FIXME
    // // @FIXME
    // // This looks like another module's variable. You'll need to rewrite this call
    // // to ensure that it uses the correct configuration object.
    // $form['upload_project_title_resource_file']['project_title_resource_file_path'] = array(
    // 		'#type' => 'file',
    // 		'#size' => 48,
    // 		'#description' => t('<span style="color:red;">Upload filenames with allowed extensions only. No spaces or any special characters allowed in filename.</span>') . '<br />' . t('<span style="color:red;">Allowed file extensions: ') . variable_get('list_of_available_projects_file', '') . '</span>'
    // 	);
    $form['upload_project_title_resource_file']['project_title_resource_file_path'] = [
      '#type' => 'file',
      // '#size' => 48,
      '#description' => $this->t('<span style="color:red;">Upload filenames with allowed extensions only. No spaces or any special characters allowed in filename.</span>') . '<br />' . $this->t('<span style="color:red;">Allowed file extensions: ') . \Drupal::config('cfd_case_study.settings')->get('list_of_available_projects_file') . '</span>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
    ];
    return $form;
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $files = \Drupal::request()->files->get('files') ?? [];
    $upload = $files['project_title_resource_file_path'] ?? NULL;
    if (!$upload || !$upload->isValid()) {
      $form_state->setErrorByName('project_title_resource_file_path', $this->t('Please upload the file'));
      return;
    }

    $allowed_extensions_str = \Drupal::config('cfd_case_study.settings')->get('list_of_available_projects_file');
    $allowed_extensions = array_filter(array_map('trim', explode(',', (string) $allowed_extensions_str)));
    $original_name = (string) $upload->getClientOriginalName();
    $temp_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (!empty($allowed_extensions) && !in_array($temp_extension, $allowed_extensions, TRUE)) {
      $form_state->setErrorByName('project_title_resource_file_path', $this->t('Only file with @ext extensions can be uploaded.', ['@ext' => $allowed_extensions_str]));
    }
    if ($upload->getSize() <= 0) {
      $form_state->setErrorByName('project_title_resource_file_path', $this->t('File size cannot be zero.'));
    }
    if (!cfd_case_study_check_valid_filename($original_name)) {
      $form_state->setErrorByName('project_title_resource_file_path', $this->t('Invalid file name specified. Only alphabets and numbers are allowed as a valid filename.'));
    }
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $v = $form_state->getValues();
    $result1 = \Drupal::database()->insert('list_of_project_titles')
      ->fields([
        'project_title_name' => $v['new_project_title_name'],
      ])
      ->execute();
    $dest_path = cfd_case_study_project_titles_resource_file_path();
    //var_dump($dest_path);die;
    $files = \Drupal::request()->files->get('files') ?? [];
    $upload = $files['project_title_resource_file_path'] ?? NULL;
    if ($upload && $upload->isValid()) {
      $file_system = \Drupal::service('file_system');
      $file_system->prepareDirectory($dest_path, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
      $original_name = $file_system->basename($upload->getClientOriginalName());
      $target_name = $result1 . '_' . $original_name;
      $target_path = $dest_path . $target_name;

      if (file_exists($target_path)) {
        $this->messenger()->addError($this->t('Error uploading file. File @filename already exists.', ['@filename' => $original_name]));
      }
      else {
        $upload->move($dest_path, $target_name);
        \Drupal::database()->update('list_of_project_titles')
          ->fields(['filepath' => $target_name])
          ->condition('id', $result1)
          ->execute();
        $this->messenger()->addStatus($this->t('@filename uploaded successfully.', ['@filename' => $original_name]));
      }
    }
    \Drupal::messenger()->addStatus(t('Project title added successfully'));
    \Drupal\Core\Cache\Cache::invalidateTags(['case_study_project_titles_list']);
  }

}
?>
