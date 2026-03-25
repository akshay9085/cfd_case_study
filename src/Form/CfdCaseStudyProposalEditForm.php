<?php

/**
 * @file
 * Contains \Drupal\cfd_case_study\Form\CfdCaseStudyProposalEditForm.
 */

namespace Drupal\cfd_case_study\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class CfdCaseStudyProposalEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cfd_case_study_proposal_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $proposal_id = $this->getProposalId();
    if (!$proposal_id) {
      $this->messenger()->addError($this->t('Invalid proposal selected. Please try again.'));
      $form_state->setRedirect('cfd_case_study.proposal_all');
      return [];
    }

    $proposal_data = $this->loadProposal($proposal_id);
    if (!$proposal_data) {
      $this->messenger()->addError($this->t('Invalid proposal selected. Please try again.'));
      $form_state->setRedirect('cfd_case_study.proposal_all');
      return [];
    }

   $user_data = \Drupal::entityTypeManager()->getStorage('user')->load($proposal_data->uid);
    $user_email = $user_data ? $user_data->getEmail() : '';
    $form['name_title'] = [
      '#type' => 'select',
      '#title' => t('Title'),
      '#options' => [
        'Dr' => 'Dr',
        'Prof' => 'Prof',
        'Mr' => 'Mr',
        'Ms' => 'Ms',
      ],
      '#required' => TRUE,
      '#default_value' => $proposal_data->name_title,
    ];
    $form['contributor_name'] = [
      '#type' => 'textfield',
      '#title' => t('Name of the Proposer'),
      '#size' => 30,
      '#maxlength' => 50,
      '#required' => TRUE,
      '#default_value' => $proposal_data->contributor_name,
    ];
    $form['student_email_id'] = [
      '#type' => 'item',
      '#title' => t('Email'),
      '#markup' => $user_email,
    ];
    $form['university'] = [
      '#type' => 'textfield',
      '#title' => t('University/Institute'),
      '#size' => 200,
      '#maxlength' => 200,
      '#required' => TRUE,
      '#default_value' => $proposal_data->university,
    ];
    $form['institute'] = [
      '#type' => 'textfield',
      '#title' => t('Institute'),
      '#size' => 80,
      '#maxlength' => 200,
      '#required' => TRUE,
      '#default_value' => $proposal_data->institute,
    ];
    $form['how_did_you_know_about_project'] = [
      '#type' => 'textfield',
      '#title' => t('How did you come to know about the Case Study Project?'),
      '#default_value' => $proposal_data->how_did_you_know_about_project,
      '#required' => TRUE,
    ];
    $form['faculty_name'] = [
      '#type' => 'textfield',
      '#title' => t('Name of the Faculty'),
      '#size' => 50,
      '#maxlength' => 50,
      '#validated' => TRUE,
      '#default_value' => $proposal_data->faculty_name,
    ];
    $form['faculty_department'] = [
      '#type' => 'textfield',
      '#title' => t('Department of the Faculty'),
      '#size' => 50,
      '#maxlength' => 50,
      '#validated' => TRUE,
      '#default_value' => $proposal_data->faculty_department,
    ];
    $form['faculty_email'] = [
      '#type' => 'textfield',
      '#title' => t('Email id of the Faculty'),
      '#size' => 255,
      '#maxlength' => 255,
      '#validated' => TRUE,
      '#default_value' => $proposal_data->faculty_email,
    ];
    $form['country'] = [
      '#type' => 'select',
      '#title' => t('Country'),
      '#options' => [
        'India' => 'India',
        'Others' => 'Others',
      ],
      '#default_value' => $proposal_data->country,
      '#required' => TRUE,
      '#tree' => TRUE,
      '#validated' => TRUE,
    ];
    $form['other_country'] = [
      '#type' => 'textfield',
      '#title' => t('Other than India'),
      '#size' => 100,
      '#default_value' => $proposal_data->country,
      '#attributes' => [
        'placeholder' => t('Enter your country name')
        ],
      '#states' => [
        'visible' => [
          ':input[name="country"]' => [
            'value' => 'Others'
            ]
          ]
        ],
    ];
    $form['other_state'] = [
      '#type' => 'textfield',
      '#title' => t('State other than India'),
      '#size' => 100,
      '#attributes' => [
        'placeholder' => t('Enter your state/region name')
        ],
      '#default_value' => $proposal_data->state,
      '#states' => [
        'visible' => [
          ':input[name="country"]' => [
            'value' => 'Others'
            ]
          ]
        ],
    ];
    $form['other_city'] = [
      '#type' => 'textfield',
      '#title' => t('City other than India'),
      '#size' => 100,
      '#attributes' => [
        'placeholder' => t('Enter your city name')
        ],
      '#default_value' => $proposal_data->city,
      '#states' => [
        'visible' => [
          ':input[name="country"]' => [
            'value' => 'Others'
            ]
          ]
        ],
    ];
    $form['all_state'] = [
      '#type' => 'select',
      '#title' => t('State'),
      '#options' => _df_list_of_states(),
      '#default_value' => $proposal_data->state,
      '#validated' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="country"]' => [
            'value' => 'India'
            ]
          ]
        ],
    ];
    $form['city'] = [
      '#type' => 'select',
      '#title' => t('City'),
      '#options' => _df_list_of_cities(),
      '#default_value' => $proposal_data->city,
      '#states' => [
        'visible' => [
          ':input[name="country"]' => [
            'value' => 'India'
            ]
          ]
        ],
    ];
    $form['pincode'] = [
      '#type' => 'textfield',
      '#title' => t('Pincode'),
      '#size' => 30,
      '#maxlength' => 6,
      '#default_value' => $proposal_data->pincode,
      '#attributes' => [
        'placeholder' => 'Insert pincode of your city/ village....'
        ],
    ];
    $form['project_title'] = [
      '#type' => 'textfield',
      '#title' => t('Title of the Case Study Project'),
      '#size' => 300,
      '#maxlength' => 350,
      '#required' => TRUE,
      '#default_value' => $proposal_data->project_title,
    ];
    $version_options = _cs_list_of_versions();
    $form['version'] = [
      '#type' => 'select',
      '#title' => t('Version used'),
      '#options' => $version_options,
      '#default_value' => $proposal_data->version_id,
    ];
    $simulation_type_options = _cs_list_of_simulation_types();
    $form['simulation_type'] = [
      '#type' => 'select',
      '#title' => t('Simulation Type used'),
      '#options' => $simulation_type_options,
      '#default_value' => $proposal_data->simulation_type_id,
      '#ajax' => [
        'callback' => '::ajaxSolverUsedCallback',
        'event' => 'change',
        ],
    ];
    $simulation_id = (int) $proposal_data->simulation_type_id;
    if ($form_state->hasValue('simulation_type') && $form_state->getValue('simulation_type') !== '') {
      $simulation_id = (int) $form_state->getValue('simulation_type');
    }

    $form['solver_used'] = [
      '#type' => 'select',
      '#title' => t('Select the Solver to be used'),
      '#options' => _cs_list_of_solvers($simulation_id),
      '#prefix' => '<div id="ajax-solver-replace">',
      '#suffix' => '</div>',
      '#states' => [
        'invisible' => [
          ':input[name="simulation_type"]' => [
            'value' => 19
            ]
          ]
        ],
      //'#required' => TRUE
        '#default_value' => $proposal_data->solver_used,
    ];

    $form['solver_used_text'] = [
      '#type' => 'textfield',
      '#title' => t('Enter the Solver to be used'),
      '#size' => 100,
      '#description' => t('Maximum character limit is 50'),
      //'#required' => TRUE,
        '#prefix' => '<div id="ajax-solver-text-replace">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="simulation_type"]' => [
            'value' => 19
            ]
          ]
        ],
      '#default_value' => $proposal_data->solver_used,
    ];
    /* $form['solver_used'] = array(
        '#type' => 'textfield',
        '#title' => t('Solver to be used'),
        '#size' => 50,
        '#maxlength' => 50,
        '#required' => true,
        '#default_value' => $proposal_data->solver_used,
    );*/
    $form['date_of_proposal'] = [
      '#type' => 'textfield',
      '#title' => t('Date of Proposal'),
      '#default_value' => date('d/m/Y', $proposal_data->creation_date),
      '#disabled' => TRUE,
    ];
    $form['delete_proposal'] = [
      '#type' => 'checkbox',
      '#title' => t('Delete Proposal'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
    ];
    $form['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#limit_validation_errors' => [],
      '#submit' => ['::cancelForm'],
    ];

    return $form;
  }

  /**
   * Ajax callback for refreshing solver fields when simulation type changes.
   */
  public function ajaxSolverUsedCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#ajax-solver-replace', $form['solver_used']));
    $response->addCommand(new ReplaceCommand('#ajax-solver-text-replace', $form['solver_used_text']));

    return $response;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue(['simulation_type']) < 19) {
      if ($form_state->getValue(['solver_used']) == '0') {
        $form_state->setErrorByName('solver_used', t('Please select an option'));
      }
    }
    else {
      if ($form_state->getValue(['simulation_type']) == 19) {
        if ($form_state->getValue(['solver_used_text']) != '') {
          if (strlen($form_state->getValue(['solver_used_text'])) > 100) {
            $form_state->setErrorByName('solver_used_text', t('Maximum charater limit is 100 charaters only, please check the length of the solver used'));
          } //strlen($form_state['values']['project_title']) > 250
          else {
            if (strlen($form_state->getValue(['solver_used_text'])) < 7) {
              $form_state->setErrorByName('solver_used_text', t('Minimum charater limit is 7 charaters, please check the length of the solver used'));
            }
          } //strlen($form_state['values']['project_title']) < 10
        }
        else {
          $form_state->setErrorByName('solver_used_text', t('Solver used cannot be empty'));
        }
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $proposal_id = $this->getProposalId();
    if (!$proposal_id) {
      $this->messenger()->addError($this->t('Invalid proposal selected. Please try again.'));
      $form_state->setRedirect('cfd_case_study.proposal_all');
      return;
    }

    $proposal_data = $this->loadProposal($proposal_id);
    if (!$proposal_data) {
      $this->messenger()->addError($this->t('Invalid proposal selected. Please try again.'));
      $form_state->setRedirect('cfd_case_study.proposal_all');
      return;
    }
    /* delete proposal */
    if ($form_state->getValue(['delete_proposal']) == 1) {
      /* sending email */
      $user_data = \Drupal::entityTypeManager()->getStorage('user')->load($proposal_data->uid);
      $email_to = $user_data ? $user_data->getEmail() : '';
      $config = \Drupal::config('cfd_case_study.settings');
      $from = $config->get('case_study_from_email') ?: \Drupal::config('system.site')->get('mail');
      if (empty($from)) {
        $from = 'no-reply@localhost';
      }
      $bcc = $config->get('case_study_emails');
      $cc = $config->get('case_study_cc_emails');
      $langcode = $user_data ? $user_data->getPreferredLangcode() : $this->currentUser()->getPreferredLangcode();

      $params['case_study_proposal_deleted']['proposal_id'] = $proposal_id;
      $params['case_study_proposal_deleted']['user_id'] = $proposal_data->uid;
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
      $params['case_study_proposal_deleted']['headers'] = $headers;
      $mail_manager = \Drupal::service('plugin.manager.mail');
      if ($email_to) {
        $result = $mail_manager->mail('cfd_case_study', 'case_study_proposal_deleted', $email_to, $langcode, $params, $from, TRUE);
        if (empty($result['result'])) {
          \Drupal::messenger()->addError('Error sending email message.');
        }
      }

      \Drupal::messenger()->addStatus(t('Case Study proposal has been deleted.'));
      if (rrmdir_project($proposal_id) == TRUE) {
        $query = \Drupal::database()->delete('case_study_proposal');
        $query->condition('id', $proposal_id);
        $num_deleted = $query->execute();
        \Drupal::messenger()->addStatus(t('Proposal Deleted'));
        \Drupal\Core\Cache\Cache::invalidateTags([
          'case_study_proposal_list',
          "case_study_proposal:$proposal_id",
        ]);
        $form_state->setRedirect('cfd_case_study.proposal_all');
        return;
      } //rrmdir_project($proposal_id) == TRUE
    } //$form_state['values']['delete_proposal'] == 1
    /* update proposal */
    $v = $form_state->getValues();
    $project_title = $v['project_title'];
    $proposar_name = $v['name_title'] . ' ' . $v['contributor_name'];
    $university = $v['university'];
    $directory_names = _df_dir_name($project_title, $proposar_name);
    if (DF_RenameDir($proposal_id, $directory_names)) {
      $directory_name = $directory_names;
    } //LM_RenameDir($proposal_id, $directory_names)
    else {
      return;
    }
    $simulation_id = $v['simulation_type'];
    if ($simulation_id < 19) {
      $solver = $v['solver_used'];
    }
    else {
      $solver = $v['solver_used_text'];
    }
    $query = "UPDATE case_study_proposal SET
				name_title=:name_title,
				contributor_name=:contributor_name,
				university=:university,
				institute=:institute,
				how_did_you_know_about_project = :how_did_you_know_about_project,
				faculty_name = :faculty_name,
				faculty_department = :faculty_department,
				faculty_email = :faculty_email,
				city=:city,
				pincode=:pincode,
				state=:state,
				project_title=:project_title,
                version_id=:version_id,
                simulation_type_id=:simulation_type_id,
				solver_used=:solver_used,
				directory_name=:directory_name
				WHERE id=:proposal_id";
    $args = [
      ':name_title' => $v['name_title'],
      ':contributor_name' => $v['contributor_name'],
      ':university' => $v['university'],
      ":institute" => $v['institute'],
      ":how_did_you_know_about_project" => $v['how_did_you_know_about_project'],
      ":faculty_name" => $v['faculty_name'],
      ":faculty_department" => $v['faculty_department'],
      ":faculty_email" => $v['faculty_email'],
      ':city' => $v['city'],
      ':pincode' => $v['pincode'],
      ':state' => $v['all_state'],
      ':project_title' => $project_title,
      ':version_id' => $v['version'],
      ':simulation_type_id' => $simulation_id,
      ":solver_used" => $solver,
      ':directory_name' => $directory_name,
      ':proposal_id' => $proposal_id,
    ];
    $result = \Drupal::database()->query($query, $args);
    \Drupal::messenger()->addStatus(t('Proposal Updated'));
    \Drupal\Core\Cache\Cache::invalidateTags([
      'case_study_proposal_list',
      "case_study_proposal:$proposal_id",
    ]);
    $form_state->setRedirect('cfd_case_study.proposal_all');
  }

  /**
   * Redirects edit form cancel action to proposal list without validation.
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('cfd_case_study.proposal_all');
  }

  /**
   * Returns the proposal entity row for a given ID.
   *
   * @param int $proposal_id
   *   The proposal identifier.
   *
   * @return object|null
   *   The proposal row or NULL if not found.
   */
  protected function loadProposal($proposal_id) {
    $query = \Drupal::database()->select('case_study_proposal');
    $query->fields('case_study_proposal');
    $query->condition('id', $proposal_id);
    $proposal_q = $query->execute();

    return $proposal_q ? $proposal_q->fetchObject() : NULL;
  }

  /**
   * Safely pull the proposal ID from the current route or query string.
   *
   * @return int|null
   *   The proposal ID if available, otherwise NULL.
   */
  protected function getProposalId() {
    $route_match = \Drupal::routeMatch();
    $proposal_id = $route_match->getParameter('id') ?: $route_match->getParameter('proposal_id');

    if (!$proposal_id) {
      $proposal_id = \Drupal::request()->query->get('id') ?: \Drupal::request()->query->get('proposal_id');
    }

    return $proposal_id !== NULL ? (int) $proposal_id : NULL;
  }

}
?>
