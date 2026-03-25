<?php

/**
 * @file
 * Contains \Drupal\cfd_case_study\Form\CfdCaseStudyAbstractBulkApprovalForm.
 */

namespace Drupal\cfd_case_study\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Link;
use Drupal\Core\Url;

class CfdCaseStudyAbstractBulkApprovalForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cfd_case_study_abstract_bulk_approval_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $options_first = $this->getCaseStudyProjectOptions();
    $selected = $form_state->getValue(['case_study_project']);
    if ($selected === NULL || $selected === '') {
      $selected = key($options_first);
    }
    $form = [];
    $form['case_study_project'] = [
      '#type' => 'select',
      '#title' => t('Title of the case study project'),
      '#options' => $options_first,
      '#default_value' => $selected,
      '#ajax' => [
        'callback' => '::ajaxBulkCaseStudyAbstractDetailsCallback',
        'event' => 'change',
        'limit_validation_errors' => [['case_study_project']],
      ],
      '#suffix' => '<div id="ajax_selected_case_study"></div><div id="ajax_selected_case_study_pdf"></div>',
    ];
    $form['case_study_actions'] = [
      '#type' => 'select',
      '#title' => t('Please select action for case study project'),
      '#options' => $this->getCaseStudyActionOptions(),
      '#default_value' => 0,
      '#prefix' => '<div id="ajax_selected_case_study_action" style="color:red;">',
      '#suffix' => '</div>',
      '#states' => [
        'invisible' => [
          ':input[name="case_study_project"]' => [
            'value' => 0
            ]
          ]
        ],
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => t('If Dis-Approved please specify reason for Dis-Approval'),
      '#prefix' => '<div id= "message_submit">',
      '#states' => [
        'visible' => [
          [
            ':input[name="case_study_actions"]' => [
              'value' => 3
              ]
            ],
          'or',
          [':input[name="case_study_actions"]' => ['value' => 4]],
        ]
        ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#states' => [
        'invisible' => [
          ':input[name="case_study_project"]' => [
            'value' => 0
          ]
        ]
      ],
    ];
    return $form;
  }

  public function ajaxBulkCaseStudyAbstractDetailsCallback(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    $response = new AjaxResponse();

    $case_study_project_default_value = $form_state->getValue('case_study_project');
    if ($case_study_project_default_value) {
      $response->addCommand(new HtmlCommand('#ajax_selected_case_study', $this->buildCaseStudyDetailsMarkup($case_study_project_default_value)));
      $response->addCommand(new ReplaceCommand('#ajax_selected_case_study_action', $form['case_study_actions']));
    }
    else {
      $response->addCommand(new HtmlCommand('#ajax_selected_case_study', ''));
    }

    return $response;
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $config = \Drupal::config('cfd_case_study.settings');
    $from = $config->get('case_study_from_email') ?: $config->get('from_email') ?: \Drupal::config('system.site')->get('mail');
    if (empty($from)) {
      $from = 'no-reply@localhost';
    }
    $bcc = $config->get('case_study_emails') ?: $config->get('emails');
    $cc = $config->get('case_study_cc_emails') ?: $config->get('cc_emails');
    $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $mail_manager = \Drupal::service('plugin.manager.mail');
    $msg = '';
    $trigger = $form_state->getTriggeringElement();
    if (($trigger['#type'] ?? '') === 'submit') {
      if ($form_state->getValue(['case_study_project']))
        //var_dump($form_state['values']['case_study_actions']);die;
        // case_study_abstract_del_lab_pdf($form_state['values']['case_study_project']);
 {
        if (\Drupal::currentUser()->hasPermission('Case Study bulk manage abstract')) {
          $query = \Drupal::database()->select('case_study_proposal');
          $query->fields('case_study_proposal');
          $query->condition('id', $form_state->getValue(['case_study_project']));
          $user_query = $query->execute();
          $user_info = $user_query->fetchObject();
          //var_dump($user_info);die;
          $user_data = \Drupal::entityTypeManager()->getStorage('user')->load($user_info->uid);
          if ($user_data && $user_data->getPreferredLangcode()) {
            $langcode = $user_data->getPreferredLangcode();
          }
          if ($form_state->getValue(['case_study_actions']) == 1) {
            // approving entire project //
            $query = \Drupal::database()->select('case_study_submitted_abstracts');
            $query->fields('case_study_submitted_abstracts');
            $query->condition('proposal_id', $form_state->getValue(['case_study_project']));
            $abstracts_q = $query->execute();
            //var_dump($abstracts_q);die;
            $experiment_list = '';
            while ($abstract_data = $abstracts_q->fetchObject()) {
              \Drupal::database()->query("UPDATE {case_study_submitted_abstracts} SET abstract_approval_status = 1, is_submitted = 1, approver_uid = :approver_uid WHERE id = :id", [
                ':approver_uid' => $user->id(),
                ':id' => $abstract_data->id,
              ]);
              \Drupal::database()->query("UPDATE {case_study_submitted_abstracts_file} SET file_approval_status = 1, approvar_uid = :approver_uid WHERE submitted_abstract_id = :submitted_abstract_id", [
                ':approver_uid' => $user->id(),
                ':submitted_abstract_id' => $abstract_data->id,
              ]);
            } //$abstract_data = $abstracts_q->fetchObject()
            \Drupal::messenger()->addStatus(t('Approved case study project.'));
            // email 
            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $email_subject = t('[!site_name][case study Project] Your uploaded case study project have been approved', array(
            // 						'!site_name' => variable_get('site_name', '')
            // 					));

            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $email_body = array(
            // 						0 => t('
            // 
            // Dear !user_name,
            // 
            // Your uploaded abstract for the case study project has been approved:
            // 
            // Title of case study project  : ' . $user_info->project_title . '
            // 
            // Best Wishes,
            // 
            // !site_name Team,
            // FOSSEE,IIT Bombay', array(
            // 							'!site_name' => variable_get('site_name', ''),
            // 							'!user_name' => $user_data->name
            // 						))
            // 					);

            /** sending email when everything done **/
            $email_to = $user_data ? $user_data->getEmail() : '';
            if ($email_to) {
              $params = $this->buildBulkMailParams(
                'case_study_bulk_project_approved',
                $form_state->getValue(['case_study_project']),
                (int) $user_info->uid,
                $from,
                $cc,
                $bcc
              );
              $result = $mail_manager->mail('cfd_case_study', 'case_study_bulk_project_approved', $email_to, $langcode, $params, $from, TRUE);
              if (empty($result['result'])) {
                $msg = \Drupal::messenger()->addError('Error sending email message.');
              }
            } //!drupal_mail('cfd_case_study', 'standard', $email_to, language_default(), $params, $from, TRUE)
          } //$form_state['values']['case_study_actions'] == 1
          elseif ($form_state->getValue(['case_study_actions']) == 2) {
            //pending review entire project 
            $query = \Drupal::database()->select('case_study_submitted_abstracts');
            $query->fields('case_study_submitted_abstracts');
            $query->condition('proposal_id', $form_state->getValue(['case_study_project']));
            $abstracts_q = $query->execute();
            $experiment_list = '';
            while ($abstract_data = $abstracts_q->fetchObject()) {
              \Drupal::database()->query("UPDATE {case_study_submitted_abstracts} SET abstract_approval_status = 0, is_submitted = 0, approver_uid = :approver_uid WHERE id = :id", [
                ':approver_uid' => $user->id(),
                ':id' => $abstract_data->id,
              ]);
              \Drupal::database()->query("UPDATE {case_study_proposal} SET is_submitted = 0, approver_uid = :approver_uid WHERE id = :id", [
                ':approver_uid' => $user->id(),
                ':id' => $abstract_data->proposal_id,
              ]);
              \Drupal::database()->query("UPDATE {case_study_submitted_abstracts_file} SET file_approval_status = 0, approvar_uid = :approver_uid WHERE submitted_abstract_id = :submitted_abstract_id", [
                ':approver_uid' => $user->id(),
                ':submitted_abstract_id' => $abstract_data->id,
              ]);
            } //$abstract_data = $abstracts_q->fetchObject()
            \Drupal::messenger()->addStatus(t('Resubmit the project files'));
            // email 
            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $email_subject = t('[!site_name][case study Project] Your uploaded case study project have been marked as pending', array(
            // 						'!site_name' => variable_get('site_name', '')
            // 					));

            // @FIXME
            // // @FIXME
            // // This looks like another module's variable. You'll need to rewrite this call
            // // to ensure that it uses the correct configuration object.
            // $email_body = array(
            // 						0 => t('
            // 
            // Dear !user_name,
            // 
            // Kindly resubmit the project files for the project : ' . $user_info->project_title . '.
            // 
            // 
            // Best Wishes,
            // 
            // !site_name Team,
            // FOSSEE,IIT Bombay', array(
            // 							'!site_name' => variable_get('site_name', ''),
            // 							'!user_name' => $user_data->name
            // 						))
            // 					);

            /** sending email when everything done **/
            $email_to = $user_data ? $user_data->getEmail() : '';
            if ($email_to) {
              $params = $this->buildBulkMailParams(
                'case_study_bulk_project_resubmit',
                $form_state->getValue(['case_study_project']),
                (int) $user_info->uid,
                $from,
                $cc,
                $bcc
              );
              $result = $mail_manager->mail('cfd_case_study', 'case_study_bulk_project_resubmit', $email_to, $langcode, $params, $from, TRUE);
              if (empty($result['result'])) {
                \Drupal::messenger()->addError('Error sending email message.');
              }
            } //!drupal_mail('cfd_case_study', 'standard', $email_to, language_default(), $params, $from, TRUE)
          } //$form_state['values']['case_study_actions'] == 2
          elseif ($form_state->getValue(['case_study_actions']) == 3) //disapprove and delete entire case study project
 {
            if (strlen(trim($form_state->getValue(['message']))) <= 30) {
              $form_state->setErrorByName('message', t(''));
              $msg = \Drupal::messenger()->addError("Please mention the reason for disapproval. Minimum 30 character required");
              return $msg;
            } //strlen(trim($form_state['values']['message'])) <= 30
            if (!\Drupal::currentUser()->hasPermission('Case Study bulk delete abstract')) {
              $msg = \Drupal::messenger()->addError(t('You do not have permission to Bulk Dis-Approved and Deleted Entire Lab.'));
              return $msg;
            } //!user_access('case_study bulk delete code')
            if ($this->deleteCaseStudyProject($form_state->getValue(['case_study_project']))) //////
 {
              \Drupal::messenger()->addStatus(t('Dis-Approved and Deleted Entire case study project.'));
              // @FIXME
              // // @FIXME
              // // This looks like another module's variable. You'll need to rewrite this call
              // // to ensure that it uses the correct configuration object.
              // $email_subject = t('[!site_name][case study Project] Your uploaded case study project have been marked as dis-approved', array(
              // 						'!site_name' => variable_get('site_name', '')
              // 					));

              // @FIXME
              // // @FIXME
              // // This looks like another module's variable. You'll need to rewrite this call
              // // to ensure that it uses the correct configuration object.
              // $email_body = array(
              // 						0 => t('
              // Dear !user_name,
              // 
              // Your uploaded case study project files for the case study project Title : ' . $user_info->project_title . ' have been marked as dis-approved.
              // 
              // Reason for dis-approval: ' . $form_state['values']['message'] . '
              // 
              // Best Wishes,
              // 
              // !site_name Team,
              // FOSSEE,IIT Bombay', array(
              // 						'!site_name' => variable_get('site_name', ''),
              // 						'!user_name' => $user_data->name
              // 											))
              // 					);

              $email_to = $user_data ? $user_data->getEmail() : '';
              if ($email_to) {
                $params = $this->buildBulkMailParams(
                  'case_study_bulk_project_disapproved',
                  $form_state->getValue(['case_study_project']),
                  (int) $user_info->uid,
                  $from,
                  $cc,
                  $bcc,
                  ['reason' => trim((string) $form_state->getValue(['message']))]
                );
                $result = $mail_manager->mail('cfd_case_study', 'case_study_bulk_project_disapproved', $email_to, $langcode, $params, $from, TRUE);
                if (empty($result['result'])) {
                  \Drupal::messenger()->addError('Error sending email message.');
                }
              }
            }
            else {
              \Drupal::messenger()->addError(t('Error Dis-Approving and Deleting Entire case study project.'));
            }
            // email 

          } //$form_state['values']['case_study_actions'] == 3

        }
      } //user_access('case_study project bulk manage code')
      \Drupal\Core\Cache\Cache::invalidateTags([
        'case_study_proposal_list',
        'case_study_project_titles_list',
        'case_study_proposal:' . (int) $form_state->getValue(['case_study_project']),
      ]);
      return $msg;
    } //$form_state['clicked_button']['#value'] == 'Submit'
  }

  /**
   * Returns the selectable list of submitted case study projects.
   */
  protected function getCaseStudyProjectOptions() {
    $project_titles = [
      0 => $this->t('Please select...'),
    ];

    $query = \Drupal::database()->select('case_study_proposal', 'csp')
      ->fields('csp', ['id', 'project_title', 'contributor_name'])
      ->condition('is_submitted', 1)
      ->condition('approval_status', 1)
      ->orderBy('project_title', 'ASC');

    foreach ($query->execute() as $project) {
      $project_titles[$project->id] = $project->project_title . ' (Proposed by ' . $project->contributor_name . ')';
    }

    return $project_titles;
  }

  /**
   * Returns the available bulk actions.
   */
  protected function getCaseStudyActionOptions() {
    return [
      0 => $this->t('Please select...'),
      1 => $this->t('Approve Entire case study Project'),
      2 => $this->t('Resubmit Project files'),
      3 => $this->t('Dis-Approve Entire case study Project (This will delete case study Project)'),
    ];
  }

  /**
   * Builds the case study details HTML shown for the selected project.
   */
  protected function buildCaseStudyDetailsMarkup($proposal_id) {
    $proposal = \Drupal::database()->select('case_study_proposal', 'csp')
      ->fields('csp')
      ->condition('id', (int) $proposal_id)
      ->execute()
      ->fetchObject();

    if (!$proposal) {
      return '';
    }

    $abstract_file = \Drupal::database()->select('case_study_submitted_abstracts_file', 'cssf')
      ->fields('cssf', ['filename'])
      ->condition('proposal_id', (int) $proposal_id)
      ->condition('filetype', 'A')
      ->execute()
      ->fetchField();

    $project_file = \Drupal::database()->select('case_study_submitted_abstracts_file', 'cssf')
      ->fields('cssf', ['filename'])
      ->condition('proposal_id', (int) $proposal_id)
      ->condition('filetype', 'S')
      ->execute()
      ->fetchField();

    $download_case_study = Link::fromTextAndUrl(
      $this->t('Download case study project'),
      Url::fromRoute('cfd_case_study.download_full_project', [], [
        'query' => ['id' => (int) $proposal_id],
      ])
    )->toString();

    return '<strong>' . $this->t('Proposer Name:') . '</strong><br />'
      . Html::escape(trim($proposal->name_title . ' ' . $proposal->contributor_name)) . '<br /><br />'
      . '<strong>' . $this->t('Title of the case study Project:') . '</strong><br />'
      . Html::escape($proposal->project_title) . '<br /><br />'
      . '<strong>' . $this->t('Uploaded an abstract (brief outline) of the project:') . '</strong><br />'
      . Html::escape($this->normalizeUploadedFilename($abstract_file)) . '<br /><br />'
      . '<strong>' . $this->t('Uploaded Case Directory Folder:') . '</strong><br />'
      . Html::escape($this->normalizeUploadedFilename($project_file)) . '<br /><br />'
      . '<strong>' . $this->t('Download Case Study Project:') . '</strong><br />'
      . $download_case_study;
  }

  /**
   * Returns a display value for an uploaded filename.
   */
  protected function normalizeUploadedFilename($filename) {
    if ($filename === FALSE || $filename === NULL || $filename === '' || $filename === 'NULL') {
      return $this->t('File not uploaded');
    }

    return $filename;
  }

  /**
   * Deletes all files and records for a case study project.
   */
  protected function deleteCaseStudyProject($proposal_id) {
    $proposal = \Drupal::database()->select('case_study_proposal', 'csp')
      ->fields('csp')
      ->condition('id', (int) $proposal_id)
      ->execute()
      ->fetchObject();

    if (!$proposal) {
      $this->messenger()->addError($this->t('Invalid Case Study Project.'));
      return FALSE;
    }

    $directory = rtrim(cfd_case_study_path(), '/\\') . '/' . $proposal->directory_name;
    if (is_dir($directory) && !$this->removeDirectory($directory)) {
      $this->messenger()->addError($this->t('Unable to delete the case study project directory.'));
      return FALSE;
    }

    \Drupal::database()->delete('case_study_submitted_abstracts_file')
      ->condition('proposal_id', (int) $proposal_id)
      ->execute();

    \Drupal::database()->delete('case_study_submitted_abstracts')
      ->condition('proposal_id', (int) $proposal_id)
      ->execute();

    \Drupal::database()->delete('case_study_proposal')
      ->condition('id', (int) $proposal_id)
      ->execute();

    return TRUE;
  }

  /**
   * Recursively removes a directory.
   */
  protected function removeDirectory($directory) {
    $items = scandir($directory);
    if ($items === FALSE) {
      return FALSE;
    }

    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }

      $path = $directory . '/' . $item;
      if (is_dir($path)) {
        if (!$this->removeDirectory($path)) {
          return FALSE;
        }
      }
      elseif (file_exists($path) && !unlink($path)) {
        return FALSE;
      }
    }

    return rmdir($directory);
  }

  /**
   * Builds params for bulk approval notification emails.
   */
  protected function buildBulkMailParams($key, $proposal_id, $user_id, $from, $cc = '', $bcc = '', array $extra = []) {
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

    $params = [
      $key => [
        'proposal_id' => (int) $proposal_id,
        'user_id' => (int) $user_id,
        'headers' => $headers,
      ],
    ];

    if (!empty($extra)) {
      $params[$key] = array_merge($params[$key], $extra);
    }

    return $params;
  }

}
?>
