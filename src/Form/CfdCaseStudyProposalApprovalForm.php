<?php

/**
 * @file
 * Contains \Drupal\cfd_case_study\Form\CfdCaseStudyProposalApprovalForm.
 */

namespace Drupal\cfd_case_study\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxy;

class CfdCaseStudyProposalApprovalForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cfd_case_study_proposal_approval_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    /* get current proposal */
    $route_match = \Drupal::routeMatch();

    $proposal_id = (int) $route_match->getParameter('id');
    $query = \Drupal::database()->select('case_study_proposal');
    $query->fields('case_study_proposal');
    $query->condition('id', $proposal_id);
    $proposal_q = $query->execute();
    $query_abstract = \Drupal::database()->select('case_study_submitted_abstracts_file');
    $query_abstract->fields('case_study_submitted_abstracts_file');
    $query_abstract->condition('proposal_id', $proposal_id);
    $query_abstract->condition('filetype', 'A');
    $query_abstract_pdf = $query_abstract->execute()->fetchObject();
    if ($proposal_q) {
      if ($proposal_data = $proposal_q->fetchObject()) {
        /* everything ok */
      } //$proposal_data = $proposal_q->fetchObject()
      else {
        \Drupal::messenger()->addError(t('Invalid proposal selected. Please try again.'));
        // drupal_goto('case-study-project/manage-proposal');
        return;
      }
    } //$proposal_q
    else {
      \Drupal::messenger()->addError(t('Invalid proposal selected. Please try again.'));
      // drupal_goto('case-study-project/manage-proposal');
      return;
    }
    if ($proposal_data->faculty_name == '') {
      $faculty_name = 'NA';
    }
    else {
      $faculty_name = $proposal_data->faculty_name;
    }
    if ($proposal_data->faculty_department == '') {
      $faculty_department = 'NA';
    }
    else {
      $faculty_department = $proposal_data->faculty_department;
    }
    if ($proposal_data->faculty_email == '') {
      $faculty_email = 'NA';
    }
    else {
      $faculty_email = $proposal_data->faculty_email;
    }
    $query = \Drupal::database()->select('case_study_software_version');
    $query->fields('case_study_software_version');
    $query->condition('id', $proposal_data->version_id);
    $version_data = $query->execute()->fetchObject();
    $version = $version_data->case_study_version;
    $query = \Drupal::database()->select('case_study_simulation_type');
    $query->fields('case_study_simulation_type');
    $query->condition('id', $proposal_data->simulation_type_id);
    $simulation_type_data = $query->execute()->fetchObject();
    $simulation_type = $simulation_type_data->simulation_type;
    // @FIXME
    // l() expects a Url object, created from a route name or external URI.
    $form['contributor_name'] = array(
      '#title' => 'Student name',
      '#type' => 'item',
      '#markup' => Link::fromTextAndUrl(
        $proposal_data->name_title . ' ' . $proposal_data->contributor_name,
        Url::fromUserInput('/user/' . $proposal_data->uid)
      )->toString(),
      // '#size' => 250,
      '#title' => 'Student name'
    );

    $form['student_email_id'] = [
      '#title' => t('Student Email'),
      '#type' => 'item',
      // '#markup' => \Drupal::entityTypeManager()->getStorage('user')->load($proposal_data->uid)->mail,
      '#title' => t('Email'),
    ];
    $form['contributor_contact_no'] = [
      '#title' => t('Contact No.'),
      '#type' => 'item',
      '#markup' => $proposal_data->contact_no,
    ];
    $form['university'] = [
      '#type' => 'item',
      '#markup' => $proposal_data->university,
      '#title' => t('University/Institute'),
    ];
    $form['how_did_you_know_about_project'] = [
      '#type' => 'item',
      '#markup' => $proposal_data->how_did_you_know_about_project,
      '#title' => t('How did you know about the project'),
    ];
    $form['faculty_name'] = [
      '#type' => 'item',
      '#markup' => $faculty_name,
      '#title' => t('Name of the faculty'),
    ];
    $form['faculty_department'] = [
      '#type' => 'item',
      '#markup' => $faculty_department,
      '#title' => t('Department of the faculty'),
    ];
    $form['faculty_email'] = [
      '#type' => 'item',
      '#markup' => $faculty_email,
      '#title' => t('Email of the faculty'),
    ];
    $form['country'] = [
      '#type' => 'item',
      '#markup' => $proposal_data->country,
      '#title' => t('Country'),
    ];
    $form['all_state'] = [
      '#type' => 'item',
      '#markup' => $proposal_data->state,
      '#title' => t('State'),
    ];
    $form['city'] = [
      '#type' => 'item',
      '#markup' => $proposal_data->city,
      '#title' => t('City'),
    ];
    $form['pincode'] = [
      '#type' => 'item',
      '#markup' => $proposal_data->pincode,
      '#title' => t('Pincode/Postal code'),
    ];
    $form['project_title'] = [
      '#type' => 'item',
      '#markup' => $proposal_data->project_title,
      '#title' => t('Title of the Case Study Project'),
    ];
    $form['version'] = [
      '#type' => 'item',
      '#markup' => $version,
      '#title' => t('Version used'),
    ];
    $form['simulation_type'] = [
      '#type' => 'item',
      '#markup' => $simulation_type,
      '#title' => t('Simulation Type'),
    ];
    $form['solver_used'] = [
      '#type' => 'item',
      '#markup' => $proposal_data->solver_used,
      '#title' => t('Solver used'),
    ];

    $form['date_of_proposal'] = [
      '#type' => 'textfield',
      '#title' => t('Date of Proposal'),
      '#default_value' => date('d/m/Y', $proposal_data->creation_date),
      '#disabled' => TRUE,
    ];
    $form['expected_completion_date'] = [
      '#type' => 'textfield',
      '#title' => t('Expected Date of Completion'),
      '#default_value' => date('d/m/Y', $proposal_data->expected_date_of_completion),
      '#disabled' => TRUE,
    ];
   if (!empty($query_abstract_pdf->filename) && $query_abstract_pdf->filename !== 'NULL') {

  $str = substr($query_abstract_pdf->filename, strrpos($query_abstract_pdf->filename, '/'));
  $resource_file = ltrim($str, '/'); // keep same logic exactly

  $form['abstract_file_path'] = [
    '#type' => 'item',
    '#title' => $this->t('Abstract file'),
    '#markup' => Link::fromTextAndUrl(
      $resource_file,
      Url::fromRoute('cfd_case_study.project_files', ['proposal_id' => $proposal_id])
    )->toString(),
  ];
} //$proposal_data->user_defined_compound_filepath != ""
    else {
      $form['abstract_file_path'] = [
        '#type' => 'item',
        '#title' => t('Abstract file '),
        '#markup' => "Not uploaded<br><br>",
      ];
    }
    $form['approval'] = [
      '#type' => 'radios',
      '#title' => t('CFD Case Study proposal'),
      '#options' => [
        '1' => 'Approve',
        '2' => 'Disapprove',
      ],
      '#required' => TRUE,
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => t('Reason for disapproval'),
      '#attributes' => [
        'placeholder' => t('Enter reason for disapproval in minimum 30 characters '),
        'cols' => 50,
        'rows' => 4,
      ],
      '#states' => [
        'visible' => [
          ':input[name="approval"]' => [
            'value' => '2'
            ]
          ]
        ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
    ];
    // @FIXME
    // l() expects a Url object, created from a route name or external URI.
    // $form['cancel'] = array(
    //         '#type' => 'item',
    //         '#markup' => l(t('Cancel'), 'case-study-project/manage-proposal'),
    //     );

    return $form;
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    if ($form_state->getValue(['approval']) == 2) {
      if ($form_state->getValue(['message']) == '') {
        $form_state->setErrorByName('message', t('Reason for disapproval could not be empty'));
      } //$form_state['values']['message'] == ''
    } //$form_state['values']['approval'] == 2
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    /* get current proposal */
    // $proposal_id = (int) arg(3);
    $route_match = \Drupal::routeMatch();

    $proposal_id = (int) $route_match->getParameter('id');
    $query = \Drupal::database()->select('case_study_proposal');
    $query->fields('case_study_proposal');
    $query->condition('id', $proposal_id);
    $proposal_q = $query->execute();
    if ($proposal_q) {
      if ($proposal_data = $proposal_q->fetchObject()) {
        /* everything ok */
      } //$proposal_data = $proposal_q->fetchObject()
      else {
        \Drupal::messenger()->addError(t('Invalid proposal selected. Please try again.'));
        $form_state->setRedirect('cfd_case_study.proposal_pending');
        return;
      }
    } //$proposal_q
    else {
      \Drupal::messenger()->addError(t('Invalid proposal selected. Please try again.'));
      $form_state->setRedirect('cfd_case_study.proposal_pending');
      return;
    }
    if ($form_state->getValue(['approval']) == 1) {
      $query = "UPDATE {case_study_proposal} SET approver_uid = :uid, approval_date = :date, approval_status = 1 WHERE id = :proposal_id";
      $args = [
        ":uid" => $user->id(),
        ":date" => time(),
        ":proposal_id" => $proposal_id,
      ];
      \Drupal::database()->query($query, $args);
      /* sending email */
      $user_data = \Drupal::entityTypeManager()->getStorage('user')->load($proposal_data->uid);
      $email_to = $user_data ? $user_data->getEmail() : '';

      // Fetch configuration values.
      $config = \Drupal::config('cfd_case_study.settings');
      $from = $config->get('case_study_from_email') ?: \Drupal::config('system.site')->get('mail');
      if (empty($from)) {
        $from = 'no-reply@localhost';
      }
      $bcc = $config->get('case_study_emails');
      $cc = $config->get('case_study_cc_emails');

      $params['case_study_proposal_approved']['proposal_id'] = $proposal_id;
      $params['case_study_proposal_approved']['user_id'] = $proposal_data->uid;
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
      $params['case_study_proposal_approved']['headers'] = $headers;

      $langcode = $user_data ? $user_data->getPreferredLangcode() : \Drupal::languageManager()->getDefaultLanguage()->getId();
      if ($email_to) {
        $result = \Drupal::service('plugin.manager.mail')->mail('cfd_case_study', 'case_study_proposal_approved', $email_to, $langcode, $params, $from, TRUE);
        if (empty($result['result'])) {
          \Drupal::messenger()->addError('Error sending email message.');
        }
      }

      \Drupal::messenger()->addStatus('CFD Case Study proposal No. ' . $proposal_id . ' approved. User has been notified of the approval.');
      \Drupal\Core\Cache\Cache::invalidateTags([
        'case_study_proposal_list',
        "case_study_proposal:$proposal_id",
      ]);
      $form_state->setRedirect('cfd_case_study.proposal_pending');
      return;
    } //$form_state['values']['approval'] == 1
    else {
      if ($form_state->getValue(['approval']) == 2) {
        $query = "UPDATE {case_study_proposal} SET approver_uid = :uid, approval_date = :date, approval_status = 2, dissapproval_reason = :dissapproval_reason WHERE id = :proposal_id";
        $args = [
          ":uid" => $user->id(),
          ":date" => time(),
          ":dissapproval_reason" => $form_state->getValue(['message']),
          ":proposal_id" => $proposal_id,
        ];
        $result = \Drupal::database()->query($query, $args);
        /* sending email */
       $user_data = \Drupal::entityTypeManager()->getStorage('user')->load($proposal_data->uid);
$email_to = $user_data ? $user_data->getEmail() : '';


        // Fetch configuration values.
        $config = \Drupal::config('cfd_case_study.settings');
        $from = $config->get('case_study_from_email') ?: \Drupal::config('system.site')->get('mail');
        if (empty($from)) {
          $from = 'no-reply@localhost';
        }
        $bcc = $config->get('case_study_emails');
        $cc = $config->get('case_study_cc_emails');
        $params['case_study_proposal_disapproved']['proposal_id'] = $proposal_id;
        $params['case_study_proposal_disapproved']['user_id'] = $proposal_data->uid;
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
        $params['case_study_proposal_disapproved']['headers'] = $headers;

        $langcode = $user_data ? $user_data->getPreferredLangcode() : \Drupal::languageManager()->getDefaultLanguage()->getId();
        if ($email_to) {
          $result = \Drupal::service('plugin.manager.mail')->mail('cfd_case_study', 'case_study_proposal_disapproved', $email_to, $langcode, $params, $from, TRUE);
          if (empty($result['result'])) {
            \Drupal::messenger()->addError('Error sending email message.');
          }
        }

        \Drupal::messenger()->addError('CFD Case Study proposal No. ' . $proposal_id . ' dis-approved. User has been notified of the dis-approval.');
        \Drupal\Core\Cache\Cache::invalidateTags([
          'case_study_proposal_list',
          "case_study_proposal:$proposal_id",
        ]);
        $form_state->setRedirect('cfd_case_study.proposal_pending');
        return;
      }
    } //$form_state['values']['approval'] == 2
  }

}
?>
