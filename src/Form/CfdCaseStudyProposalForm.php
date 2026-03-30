<?php

/**
 * @file
 * Contains \Drupal\cfd_case_study\Form\CfdCaseStudyProposalForm.
 */

namespace Drupal\cfd_case_study\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
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


class CfdCaseStudyProposalForm extends FormBase {
  private const MODIFIED_SIMULATION_TYPE_ID = 19;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cfd_case_study_proposal_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $no_js_use = NULL) {
    $user = \Drupal::currentUser();
    /************************ start approve book details ************************/
    if ($user->id() == 0) {
      $msg = \Drupal::messenger()->addError(t('It is mandatory to @login_link on this website to access the case study proposal form. If you are a new user, please create a new account first.', [
        '@login_link' => Link::fromTextAndUrl(t('login'), Url::fromRoute('user.page'))->toString(),
      ]));
      $response = new RedirectResponse(Url::fromRoute('user.login', [], [
        'query' => \Drupal::destination()->getAsArray(),
      ])->toString());
      
      return $response;
      return $msg;
    } //$user->uid == 0
    $query = \Drupal::database()->select('case_study_proposal');
    $query->fields('case_study_proposal');
       // $query->condition('uid', $user->uid);
    $query->condition('uid', $user->id());
 
    $query->orderBy('id', 'DESC');
    $query->range(0, 1);
    $proposal_q = $query->execute();
    $proposal_data = $proposal_q->fetchObject();
    if ($proposal_data) {
      if ($proposal_data->approval_status == 0 || $proposal_data->approval_status == 1) {
        \Drupal::messenger()->addStatus(t('We have already received your proposal.'));
        $response = new RedirectResponse(Url::fromRoute('<front>')->toString());
  
  // Send the redirect response
// ->send();
        // drupal_goto('');
      
        return $response;
      } //$proposal_data->approval_status == 0 || $proposal_data->approval_status == 1
    } //$proposal_data
    $form['#attributes'] = [
      'enctype' => "multipart/form-data"
      ];

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
    ];
    $form['contributor_name'] = [
      '#type' => 'textfield',
      '#title' => t('Name of the contributor'),
      // '#size' => 250,
      '#attributes' => [
        'placeholder' => t('Enter your full name.....')
        ],
      '#maxlength' => 250,
      '#required' => TRUE,
    ];
    $form['contributor_email_id'] = [
      '#type' => 'textfield',
      '#title' => t('Email'),
      // '#size' => 30,
      '#value' => $user ? $user->getEmail() : '',
      '#disabled' => TRUE,
    ];
    $form['contributor_contact_no'] = [
      '#type' => 'textfield',
      '#title' => t('Contact No.'),
      // '#size' => 10,
      '#attributes' => [
        'placeholder' => t('Enter your contact number')
        ],
      '#maxlength' => 250,
    ];
    $form['university'] = [
      '#type' => 'textfield',
      '#title' => t('University'),
      // '#size' => 80,
      '#maxlength' => 200,
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => 'Insert full name of your university.... '
        ],
    ];
    $form['institute'] = [
      '#type' => 'textfield',
      '#title' => t('Institute'),
      // '#size' => 80,
      '#maxlength' => 200,
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => 'Insert full name of your institute.... '
        ],
    ];
    $form['how_did_you_know_about_project'] = [
      '#type' => 'select',
      '#title' => t('How did you come to know about the Case Study Project?'),
      '#options' => [
        'Poster' => 'Poster',
        'Website' => 'Website',
        'Email' => 'Email',
        'Others' => 'Others',
      ],
      '#required' => TRUE,
    ];
    $form['others_how_did_you_know_about_project'] = [
      '#type' => 'textfield',
      '#title' => t('If ‘Other’, please specify'),
      '#maxlength' => 50,
      '#description' => t('<span style="color:red">Maximum character limit is 50</span>'),
      '#states' => [
        'visible' => [
          ':input[name="how_did_you_know_about_project"]' => [
            'value' => 'Others'
            ]
          ]
        ],
    ];
    $form['faculty_name'] = [
      '#type' => 'textfield',
      '#title' => t('Name of the Faculty Member of your Institution, if any, who helped you with this Case Study Project'),
      // '#size' => 50,
      '#maxlength' => 50,
      '#validated' => TRUE,
      '#description' => t('<span style="color:red">Maximum character limit is 50</span>'),
    ];
    $form['faculty_department'] = [
      '#type' => 'textfield',
      '#title' => t('Department of the Faculty Member of your Institution, if any, who helped you with this Case Study Project'),
      // '#size' => 50,
      '#maxlength' => 50,
      '#validated' => TRUE,
      '#description' => t('<span style="color:red">Maximum character limit is 50</span>'),
    ];
    $form['faculty_email'] = [
      '#type' => 'textfield',
      '#title' => t('Email id of the Faculty Member of your Institution, if any, who helped you with this Case Study Project'),
      // '#size' => 255,
      '#maxlength' => 255,
      '#validated' => TRUE,
      '#description' => t('<span style="color:red">Maximum character limit is 255</span>'),
    ];
    $form['country'] = [
      '#type' => 'select',
      '#title' => t('Country'),
      '#options' => [
        'India' => 'India',
        'Others' => 'Others',
      ],
      '#required' => TRUE,
      '#tree' => TRUE,
      '#validated' => TRUE,
    ];
    $form['other_country'] = [
      '#type' => 'textfield',
      '#title' => t('Other than India'),
      // '#size' => 100,
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
      // '#size' => 100,
      '#attributes' => [
        'placeholder' => t('Enter your state/region name')
        ],
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
      // '#size' => 100,
      '#attributes' => [
        'placeholder' => t('Enter your city name')
        ],
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
      // '#size' => 6,
    ];
    /***************************************************************************/
    $form['hr'] = [
      '#type' => 'item',
      '#markup' => '<hr>',
    ];

    $list_case_study = _cs_list_of_case_studies();
    if (!empty($list_case_study)) {
      $form['cfd_project_title_check'] = [
        '#type' => 'radios',
        '#title' => t('Is the proposed CFD Case study from the list of available CFD Case studies?'),
        '#options' => [
          '1' => 'Yes',
          '0' => 'No',
        ],
        '#required' => TRUE,
        '#validated' => TRUE,
      ];
      $form['cfd_case_study_name_dropdown'] = [
        '#type' => 'select',
        '#title' => t('Select the name of available cfd'),
        '#required' => TRUE,
        '#options' => _cs_list_of_case_studies(),
        '#validated' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="cfd_project_title_check"]' => [
              'value' => '1'
              ]
            ]
          ],
      ];
      $form['project_title'] = [
        '#type' => 'textfield',
        '#title' => t('Project Title'),
        // '#size' => 250,
        '#description' => t('Maximum character limit is 250'),
        '#required' => TRUE,
        '#validated' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="cfd_project_title_check"]' => [
              'value' => '0'
              ]
            ]
          ],
      ];
    }
    else {
      $form['project_title'] = [
        '#type' => 'textfield',
        '#title' => t('Project Title'),
        // '#size' => 250,
        '#description' => t('Maximum character limit is 250'),
        '#required' => TRUE,
        '#validated' => TRUE,
      ];
    }
    $version_options = _cs_list_of_versions();
    $form['version'] = [
      '#type' => 'select',
      '#title' => t('Version used'),
      '#options' => $version_options,
      '#required' => TRUE,
    ];
    $simulation_type_options = _cs_list_of_simulation_types();
    $form['simulation_type'] = [
      '#type' => 'select',
      '#title' => t('Simulation Type used'),
      '#options' => $simulation_type_options,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::ajax_solver_used_callback',
        'event' => 'change',
        'wrapper' => 'ajax-solver-wrapper',
        'limit_validation_errors' => [['simulation_type']],
        ],
    ];
    $simulation_id = (int) ($form_state->getValue('simulation_type') ?: key($simulation_type_options));

    $form['solver_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-solver-wrapper'],
    ];

    if ($simulation_id < self::MODIFIED_SIMULATION_TYPE_ID) {
      $solver_options = _cs_list_of_solvers($simulation_id);
      unset($solver_options[0]);

      $form['solver_wrapper']['solver_used'] = [
        '#type' => 'select',
        '#title' => t('Select the Solver to be used'),
        '#options' => $solver_options,
        '#empty_option' => t('-Select-'),
        '#empty_value' => '0',
        '#required' => TRUE,
        '#parents' => ['solver_used'],
      ];
    }
    else {
      $form['solver_wrapper']['solver_used_text'] = [
        '#type' => 'textfield',
        '#title' => t('Enter the Solver to be used'),
        // '#size' => 100,
        '#description' => t('Maximum character limit is 50'),
        '#required' => TRUE,
        '#parents' => ['solver_used_text'],
      ];
    }

    $form['abstract_file'] = [
      '#type' => 'fieldset',
      '#title' => t('Submit an Abstract'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];
    
    $form['abstract_file']['abstract_file_path'] = [
      '#type' => 'file',
      // '#size' => 48,
      '#description' => t('<span style="color:red;">Upload filenames with allowed extensions only. No spaces or any special characters allowed in filename.</span>') . '<br />' . t('<span style="color:red;">Allowed file extensions: ') . \Drupal::config('cfd_case_study.settings')->get('resource_upload_extensions') . '</span>',
  ];
  // var_dump(\Drupal::config('cfd_case_study.settings')->get('default_allowed_extensions'));die;
    $form['date_of_proposal'] = [
      '#type' => 'date',
      '#title' => t('Date of Proposal'),
      '#default_value' => date('Y-m-d'),
      '#disabled' => TRUE,
    ];
    $form['expected_date_of_completion'] = [
      '#type' => 'date',
      '#title' => t('Expected Date of Completion'),
      '#description' => '',
      '#default_value' => '',
      '#required' => TRUE,
    ];
    $form['term_condition'] = [
      '#type' => 'checkbox',
      '#title' => t('I agree to the Terms and Conditions'),
      '#description' => t('<a href="/case-study-project/term-and-conditions" target="_blank">View Terms and Conditions</a>'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
    ];
    return $form;
  }

  // function ajax_solver_used_callback(array &$form, FormStateInterface $form_state) {
  //   return  $form['solver_used'];
  // }
  public function ajax_solver_used_callback(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['solver_wrapper'];
  }
  

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    //var_dump($form_state['values']['solver_used']);die;
    if ($form_state->getValue([
      'cfd_project_title_check'
      ]) == 1) {
      $project_title = $form_state->getValue(['cfd_case_study_name_dropdown']);
    }
    else {

      $project_title = $form_state->getValue(['project_title']);
    }
    if (!$form_state->getValue('term_condition')) {
      $form_state->setErrorByName('term_condition', t('Please accept the terms and conditions'));
    }
    if ($form_state->getValue([
      'country'
      ]) == 'Others') {
      if ($form_state->getValue(['other_country']) == '') {
        $form_state->setErrorByName('other_country', t('Enter country name'));
        // $form_state['values']['country'] = $form_state['values']['other_country'];
      } //$form_state['values']['other_country'] == ''
      else {
        $form_state->setValue(['country'], $form_state->getValue([
          'other_country'
          ]));
      }
      if ($form_state->getValue(['other_state']) == '') {
        $form_state->setErrorByName('other_state', t('Enter state name'));
        // $form_state['values']['country'] = $form_state['values']['other_country'];
      } //$form_state['values']['other_state'] == ''
      else {
        $form_state->setValue(['all_state'], $form_state->getValue([
          'other_state'
          ]));
      }
      if ($form_state->getValue(['other_city']) == '') {
        $form_state->setErrorByName('other_city', t('Enter city name'));
        // $form_state['values']['country'] = $form_state['values']['other_country'];
      } //$form_state['values']['other_city'] == ''
      else {
        $form_state->setValue(['city'], $form_state->getValue(['other_city']));
      }
    } //$form_state['values']['country'] == 'Others'
    else {
      if ($form_state->getValue(['country']) == '') {
        $form_state->setErrorByName('country', t('Select country name'));
        // $form_state['values']['country'] = $form_state['values']['other_country'];
      } //$form_state['values']['country'] == ''
      if ($form_state->getValue([
        'all_state'
        ]) == '') {
        $form_state->setErrorByName('all_state', t('Select state name'));
        // $form_state['values']['country'] = $form_state['values']['other_country'];
      } //$form_state['values']['all_state'] == ''
      if ($form_state->getValue([
        'city'
        ]) == '') {
        $form_state->setErrorByName('city', t('Select city name'));
        // $form_state['values']['country'] = $form_state['values']['other_country'];
      } //$form_state['values']['city'] == ''
    }
    //Validation for project title
    $form_state->setValue(['project_title'], trim($form_state->getValue([
      'project_title'
      ])));
    if ($form_state->getValue(['project_title']) != '') {
      if (strlen($form_state->getValue(['project_title'])) > 250) {
        $form_state->setErrorByName('project_title', t('Maximum charater limit is 250 charaters only, please check the length of the project title'));
      } //strlen($form_state['values']['project_title']) > 250
      else {
        if (strlen($form_state->getValue(['project_title'])) < 10) {
          $form_state->setErrorByName('project_title', t('Minimum charater limit is 10 charaters, please check the length of the project title'));
        }
      } //strlen($form_state['values']['project_title']) < 10
    } //$form_state['values']['project_title'] != ''
	/*else
	{
		form_set_error('project_title', t('Project title shoud not be empty'));
	}*/

    $simulation_id = (int) $form_state->getValue(['simulation_type']);
    if ($simulation_id < self::MODIFIED_SIMULATION_TYPE_ID) {
      if ($form_state->getValue(['solver_used']) == '0' || $form_state->getValue(['solver_used']) === NULL) {
        $form_state->setErrorByName('solver_used', t('Please select an option'));
      }
    }
    else {
      if ($form_state->getValue(['solver_used_text']) != '') {
        if (strlen($form_state->getValue(['solver_used_text'])) > 100) {
          $form_state->setErrorByName('solver_used_text', t('Maximum charater limit is 100 charaters only, please check the length of the solver used'));
        } //strlen($form_state['values']['project_title']) > 250
        else {
          if (strlen($form_state->getValue(['solver_used_text'])) < 7) {
            $form_state->setErrorByName('solver_used_text', t('Minimum charater limit is 7 charaters, please check the length of the solver used'));
          }
        }
      } //strlen($form_state['values']['project_title']) < 10
      else {
        $form_state->setErrorByName('solver_used_text', t('Solver used cannot be empty'));
      }
    }
    $expected_completion_value = $form_state->getValue(['expected_date_of_completion']);
    if (!empty($expected_completion_value)) {
      $expected_completion_timestamp = strtotime($expected_completion_value);
      if ($expected_completion_timestamp !== FALSE && $expected_completion_timestamp < strtotime('today')) {
        $form_state->setErrorByName('expected_date_of_completion', t('Completion date should not be earlier than proposal date'));
      }
    }

    if ($form_state->getValue(['how_did_you_know_about_project']) == 'Others') {
      if ($form_state->getValue(['others_how_did_you_know_about_project']) == '') {
        $form_state->setErrorByName('others_how_did_you_know_about_project', t('Please enter how did you know about the project'));
        // $form_state['values']['country'] = $form_state['values']['other_country'];
      } //$form_state['values']['other_country'] == ''
      else {
        $form_state->setValue(['how_did_you_know_about_project'], $form_state->getValue([
          'others_how_did_you_know_about_project'
          ]));
      }
    }
    /*if ($form_state['values']['faculty_name'] != '' || $form_state['values']['faculty_name'] != "NULL") {
		if($form_state['values']['faculty_email'] == '' || $form_state['values']['faculty_email'] == "NULL")
		{
			form_set_error('faculty_email', t('Please enter the email id of your faculty'));
		}
		if($form_state['values']['faculty_department'] == '' || $form_state['values']['faculty_department'] == 'NULL'){
			form_set_error('faculty_department', t('Please enter the Department of your faculty'));
		}
	}*/

    $files = \Drupal::request()->files->get('files') ?? [];
    $upload = $files['abstract_file_path'] ?? NULL;
    if (!$upload || !$upload->isValid()) {
      $form_state->setErrorByName('abstract_file_path', t('Please upload the abstract file'));
      return;
    }
    $allowed_extensions_str = \Drupal::config('cfd_case_study.settings')->get('resource_upload_extensions');
    $allowed_extensions = array_filter(array_map('trim', explode(',', (string) $allowed_extensions_str)));
    $original_name = (string) $upload->getClientOriginalName();
    $temp_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (!empty($allowed_extensions) && !in_array($temp_extension, $allowed_extensions, TRUE)) {
      $form_state->setErrorByName('abstract_file_path', t('Only file with ' . $allowed_extensions_str . ' extensions can be uploaded.'));
    }
    if ($upload->getSize() <= 0) {
      $form_state->setErrorByName('abstract_file_path', t('File size cannot be zero.'));
    }
    if (!cfd_case_study_check_valid_filename($original_name)) {
      $form_state->setErrorByName('abstract_file_path', t('Invalid file name specified. Only alphabets and numbers are allowed as a valid filename.'));
    }
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $user = \Drupal::currentUser();

    $root_path = cfd_case_study_path();
    if (!$user->id()) {
      \Drupal::messenger()->addError('It is mandatory to login on this website to access the proposal form');
      return;
    }
    if ($form_state->getValue(['cfd_project_title_check']) == 1) {
      $project_title = $form_state->getValue(['cfd_case_study_name_dropdown']);
    }
    else {

      $project_title = $form_state->getValue(['project_title']);
    }
    if ($form_state->getValue(['how_did_you_know_about_project']) == 'Others') {
      $how_did_you_know_about_project = $form_state->getValue(['others_how_did_you_know_about_project']);
    }
    else {
      $how_did_you_know_about_project = $form_state->getValue(['how_did_you_know_about_project']);
    }
    /* inserting the user proposal */
    $v = $form_state->getValues();
    $project_title = trim($project_title);
    $proposar_name = $v['name_title'] . ' ' . $v['contributor_name'];
    $university = $v['university'];
    $directory_name = _df_dir_name($project_title, $proposar_name);
    $simulation_id = (int) $v['simulation_type'];
    if ($simulation_id < self::MODIFIED_SIMULATION_TYPE_ID) {
      $solver = $v['solver_used'];
    }
    else {
      $solver = $v['solver_used_text'];
    }
    $expected_completion_timestamp = !empty($v['expected_date_of_completion']) ? strtotime($v['expected_date_of_completion']) : 0;
    $connection = \Drupal::database();
    $proposal_id = $connection->insert('case_study_proposal')
      ->fields([
        'uid' => $user->id(),
        'approver_uid' => 0,
        'name_title' => $v['name_title'],
        'contributor_name' => _df_sentence_case(trim($v['contributor_name'])),
        'contact_no' => $v['contributor_contact_no'],
        'university' => $v['university'],
        'institute' => _df_sentence_case($v['institute']),
        'how_did_you_know_about_project' => trim($how_did_you_know_about_project),
        'faculty_name' => $v['faculty_name'],
        'faculty_department' => $v['faculty_department'],
        'faculty_email' => $v['faculty_email'],
        'city' => $v['city'],
        'pincode' => $v['pincode'],
        'state' => $v['all_state'],
        'country' => $v['country'],
        'project_title' => $project_title,
        'version_id' => $v['version'],
        'simulation_type_id' => $simulation_id,
        'solver_used' => $solver,
        'directory_name' => $directory_name,
        'approval_status' => 0,
        'is_completed' => 0,
        'dissapproval_reason' => NULL,
        'creation_date' => time(),
        'expected_date_of_completion' => $expected_completion_timestamp,
        'approval_date' => 0,
      ])
      ->execute();

    if (!$proposal_id) {
      \Drupal::messenger()->addError(t('Error receiving your proposal. Please try again.'));
      return;
    }

    $dest_path = $directory_name . '/';
    if (!is_dir($root_path . $dest_path)) {
      mkdir($root_path . $dest_path);
    }
    /* uploading files */
    $files = \Drupal::request()->files->get('files') ?? [];
    $upload = $files['abstract_file_path'] ?? NULL;
    if ($upload && $upload->isValid()) {
      $file_system = \Drupal::service('file_system');
      $target_dir = $root_path . $dest_path;
      $file_system->prepareDirectory($target_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
      $original_name = $file_system->basename($upload->getClientOriginalName());
      $target_path = $target_dir . $original_name;

      if (file_exists($target_path)) {
        $this->messenger()->addError($this->t('Error uploading file. File @filename already exists.', ['@filename' => $original_name]));
      }
      else {
        try {
          $upload->move($target_dir, $original_name);
          $submitted_abstract_id = $connection->insert('case_study_submitted_abstracts')
            ->fields([
              'proposal_id' => $proposal_id,
              'approver_uid' => 0,
              'abstract_approval_status' => 0,
              'abstract_upload_date' => time(),
              'abstract_approval_date' => 0,
              'is_submitted' => 0,
            ])
            ->execute();
          $filemime = \Drupal::service('file.mime_type.guesser')->guessMimeType($target_path) ?: $upload->getClientMimeType();
          $filesize = filesize($target_path);
          $connection->insert('case_study_submitted_abstracts_file')
            ->fields([
              'submitted_abstract_id' => $submitted_abstract_id,
              'proposal_id' => $proposal_id,
              'uid' => $user->id(),
              'approvar_uid' => 0,
              'filename' => $original_name,
              'filepath' => $original_name,
              'filemime' => $filemime,
              'filesize' => $filesize,
              'filetype' => 'A',
              'timestamp' => time(),
            ])
            ->execute();
          $this->messenger()->addStatus($this->t('@filename uploaded successfully.', ['@filename' => $original_name]));
        }
        catch (\Exception $e) {
          $this->messenger()->addError($this->t('Error uploading file : @filename', ['@filename' => $original_name]));
        }
      }
    }
	/* sending email */
   // $email_to = $user->mail;
    // @FIXME
    // // @FIXME
    // // This looks like another module's variable. You'll need to rewrite this call
    // // to ensure that it uses the correct configuration object.
    // $form = variable_get('case_study_from_email', '');

    // @FIXME
    // // @FIXME
    // // This looks like another module's variable. You'll need to rewrite this call
    // // to ensure that it uses the correct configuration object.
    // $bcc = variable_get('case_study_emails', '');

    // @FIXME
    // // @FIXME
    // // This looks like another module's variable. You'll need to rewrite this call
    // // to ensure that it uses the correct configuration object.
    // $cc = variable_get('case_study_cc_emails', '');

    // $params['case_study_proposal_received']['proposal_id'] = $proposal_id;
    // $params['case_study_proposal_received']['user_id'] = $user->uid;
    // $params['case_study_proposal_received']['headers'] = [
    //   'From' => $form,
    //   'MIME-Version' => '1.0',
    //   'Content-Type' => 'text/plain; charset=UTF-8; format=flowed; delsp=yes',
    //   'Content-Transfer-Encoding' => '8Bit',
    //   'X-Mailer' => 'Drupal',
    //   'Cc' => $cc,
    //   'Bcc' => $bcc,
    // ];
    // if (!\Drupal::service('plugin.manager.mail')->mail('cfd_case_study', 'case_study_proposal_received', $email_to, 'en', $params, $form, TRUE)) {
    //   \Drupal::messenger()->addError('Error sending email message.');
    // }


    	/* sending email */
      
   $email_to = $user->getEmail();

    // Fetch configuration values
$config = \Drupal::config('cfd_case_study.settings'); 

$from = $config->get('case_study_from_email') ?: \Drupal::config('system.site')->get('mail');
if (empty($from)) {
  $from = 'no-reply@localhost';
}
$bcc = $config->get('case_study_emails');
$cc = $config->get('case_study_cc_emails');

// Prepare the email parameters
$params['case_study_proposal_received']['proposal_id'] = $proposal_id;
$params['case_study_proposal_received']['user_id'] = $user->id();
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
$params['case_study_proposal_received']['headers'] = $headers;

// Sending the email using Drupal's mail manager service
$langcode = $user->getPreferredLangcode() ?: \Drupal::languageManager()->getDefaultLanguage()->getId();
if ($email_to) {
  $result = \Drupal::service('plugin.manager.mail')->mail('cfd_case_study', 'case_study_proposal_received', $email_to, $langcode, $params, $from, TRUE);
  if (empty($result['result'])) {
    \Drupal::messenger()->addError('Error sending email message.');
  }
}
    // Redirect to the front page
    $form_state->setRedirectUrl(Url::fromRoute('<front>'));
    \Drupal::messenger()->addStatus(t('We have received your case study proposal. We will get back to you soon.'));
    \Drupal\Core\Cache\Cache::invalidateTags([
      'case_study_proposal_list',
      "case_study_proposal:$proposal_id",
    ]);
    // drupal_goto('');
  }

}
?>
