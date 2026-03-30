<?php /**
 * @file
 * Contains \Drupal\cfd_case_study\Controller\DefaultController.
 */

namespace Drupal\cfd_case_study\Controller;

use Drupal\cfd_case_study\Form\VerifyCertificatesForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Service;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Default controller for the cfd_case_study module.
 */
class DefaultController extends ControllerBase {

//   public function cfd_case_study_proposal_pending() {
//     /* get pending proposals to be approved */
//     $pending_rows = [];
//     $query = \Drupal::database()->select('case_study_proposal');
//     $query->fields('case_study_proposal');
//     $query->condition('approval_status', 0);
//     $query->orderBy('id', 'DESC');
//     $pending_q = $query->execute();
//     while ($pending_data = $pending_q->fetchObject()) {
//       // @FIXME
// // l() expects a Url object, created from a route name or external URI.
// // $pending_rows[$pending_data->id] = array(
// //             date('d-m-Y', $pending_data->creation_date),
// //             l($pending_data->name_title . ' ' . $pending_data->contributor_name, 'user/' . $pending_data->uid),
// //             $pending_data->project_title,
// //             l('Approve', 'case-study-project/manage-proposal/approve/' . $pending_data->id) . ' | ' . l('Edit', 'case-study-project/manage-proposal/edit/' . $pending_data->id),
// //         );

//     } //$pending_data = $pending_q->fetchObject()
//     /* check if there are any pending proposals */
//     if (!$pending_rows) {
//       \Drupal::messenger()->addStatus(t('There are no pending proposals.'));
//       return '';
//     } //!$pending_rows
//     $pending_header = [
//       'Date of Submission',
//       'Student Name',
//       'Title of the Case Study Project',
//       'Action',
//     ];
//     //$output = theme_table($pending_header, $pending_rows);
//     // @FIXME
//     // theme() has been renamed to _theme() and should NEVER be called directly.
//     // Calling _theme() directly can alter the expected output and potentially
//     // introduce security issues (see https://www.drupal.org/node/2195739). You
//     // should use renderable arrays instead.
//     // 
//     // 
//     // @see https://www.drupal.org/node/2195739
//     // $output = theme('table', array(
//     //         'header' => $pending_header,
//     //         'rows' => $pending_rows,
//     //     ));

//     return $output;
//   }
public function cfd_case_study_proposal_pending() {
  // Get pending proposals to be approved.
  $pending_rows = [];
  $query = \Drupal::database()->select('case_study_proposal', 'csp');
  $query->fields('csp', ['id', 'creation_date', 'name_title', 'contributor_name', 'uid', 'project_title']);
  $query->condition('csp.approval_status', 0);
  $query->orderBy('csp.id', 'DESC');
  $pending_q = $query->execute();
  while ($pending_data = $pending_q->fetchObject()) {
    $approval_link = Link::fromTextAndUrl(
      $this->t('Approve'),
      Url::fromRoute('cfd_case_study.proposal_approval_form', ['id' => $pending_data->id])
    )->toString();

    $edit_link = Link::fromTextAndUrl(
      $this->t('Edit'),
      Url::fromRoute('cfd_case_study.proposal_edit_form', [], [
        'query' => ['id' => $pending_data->id],
      ])
    )->toString();

    $pending_rows[] = [
      date('d-m-Y', $pending_data->creation_date),
      Link::fromTextAndUrl(
        trim($pending_data->name_title . ' ' . $pending_data->contributor_name),
        Url::fromRoute('entity.user.canonical', ['user' => $pending_data->uid])
      )->toString(),
      $pending_data->project_title,
      ['data' => ['#markup' => $approval_link . ' | ' . $edit_link]],
    ];
  }

  // Define table header.
  $pending_header = [
    $this->t('Date of Submission'),
    $this->t('Student Name'),
    $this->t('Title of the Case Study Project'),
    $this->t('Action'),
  ];

  // Render the table using renderable arrays.
  $output = [
    '#type' => 'table',
    '#header' => $pending_header,
    '#rows' => $pending_rows,
    '#empty' => $this->t('There are no pending proposals.'),
    '#attributes' => [
      'class' => ['case-study-proposal-pending-table'],
    ],
    '#cache' => [
      'tags' => ['case_study_proposal_list'],
      'contexts' => ['user.permissions', 'url.path', 'url.query_args'],
    ],
  ];

  return $output;
}


public function cfd_case_study_proposal_all() {
  $proposal_rows = [];

  $database = \Drupal::database(); // Ideally inject this.
  $query = $database->select('case_study_proposal', 'csp')
    ->fields('csp')
    ->orderBy('id', 'DESC');
  $proposal_q = $query->execute();

  foreach ($proposal_q as $proposal_data) {
    switch ($proposal_data->approval_status) {
      case 0:
        $approval_status = 'Pending';
        break;
      case 1:
        $approval_status = 'Approved';
        break;
      case 2:
        $approval_status = 'Dis-approved';
        break;
      case 3:
        $approval_status = 'Completed';
        break;
      case 5:
        $approval_status = 'On Hold';
        break;
      default:
        $approval_status = 'Unknown';
    }

    $actual_completion_date = $proposal_data->actual_completion_date == 0 ? 
      "Not Completed" : 
      date('d-m-Y', $proposal_data->actual_completion_date);

    $approval_date = $proposal_data->approval_date == 0 ? 
      "Not Approved" : 
      date('d-m-Y', $proposal_data->approval_date);

    // Links
    $user_link = Link::fromTextAndUrl(
      $proposal_data->contributor_name,
      Url::fromRoute('entity.user.canonical', ['user' => $proposal_data->uid])
    )->toString();

    $status_link = Link::fromTextAndUrl(
      'Status',
      Url::fromRoute('cfd_case_study.proposal_status_form', ['id' => $proposal_data->id])
    )->toString();

    $edit_link = Link::fromTextAndUrl(
      'Edit',
      Url::fromRoute('cfd_case_study.proposal_edit_form', ['id' => $proposal_data->id])
    )->toString();

    $action_links = $status_link . ' | ' . $edit_link;

    $proposal_rows[] = [
      date('d-m-Y', $proposal_data->creation_date),
      $user_link,
      $proposal_data->project_title,
      $approval_date,
      $actual_completion_date,
      $approval_status,
      ['data' => ['#markup' => $action_links]],
    ];
  }

  if (empty($proposal_rows)) {
    \Drupal::messenger()->addStatus(t('There are no proposals.'));
    return [
      '#markup' => t('No proposals found.'),
    ];
  }

  $proposal_header = [
    t('Date of Submission'),
    t('Student Name'),
    t('Title of the case-study project'),
    t('Date of Approval'),
    t('Date of Project Completion'),
    t('Status'),
    t('Action'),
  ];

  return [
    '#type' => 'table',
    '#header' => $proposal_header,
    '#rows' => $proposal_rows,
    '#empty' => t('No proposals available.'),
    '#cache' => [
      'tags' => ['case_study_proposal_list'],
      'contexts' => ['user.permissions', 'url.path', 'url.query_args'],
    ],
  ];
}


  public function cfd_case_study_proposal_edit_file_all() {
    /* get pending proposals to be approved */
    $proposal_rows = [];
    $query = \Drupal::database()->select('case_study_proposal', 'csp');
    $query->fields('csp', [
      'id',
      'creation_date',
      'contributor_name',
      'uid',
      'project_title',
      'approval_date',
      'actual_completion_date',
      'approval_status',
    ]);
    $query->condition('csp.approval_status', [0, 1, 2], 'NOT IN');
  
    $query->orderBy('csp.id', 'DESC');
      $query->orderBy('csp.approval_status', 'DESC');
    $proposal_q = $query->execute();
    foreach ($proposal_q as $proposal_data) {
      $approval_status = '';
      switch ($proposal_data->approval_status) {
        case 0:
          $approval_status = $this->t('Pending');
          break;
        case 1:
          $approval_status = $this->t('Approved');
          break;
        case 2:
          $approval_status = $this->t('Dis-approved');
          break;
        case 3:
          $approval_status = $this->t('Completed');
          break;
        case 5:
          $approval_status = $this->t('On Hold');
          break;
        default:
          $approval_status = $this->t('Unknown');
          break;
      } //$proposal_data->approval_status
      if ($proposal_data->actual_completion_date == 0) {
        $actual_completion_date = $this->t('Not Completed');
      } //$proposal_data->actual_completion_date == 0
      else {
        $actual_completion_date = date('d-m-Y', $proposal_data->actual_completion_date);
      }
      if ($proposal_data->approval_date == 0) {
        $approval_date = $this->t('Not Approved');
      } //$proposal_data->actual_completion_date == 0
      else {
        $approval_date = date('d-m-Y', $proposal_data->approval_date);
      }
      $edit_url = Link::fromTextAndUrl(
        $this->t('Edit'),
        Url::fromRoute('cfd_case_study.edit_upload_abstract_code_form', [], [
          'query' => ['id' => $proposal_data->id],
        ])
      )->toString();
      $proposal_rows[] = [
        date('d-m-Y', $proposal_data->creation_date),
        Link::fromTextAndUrl($proposal_data->contributor_name, Url::fromRoute('entity.user.canonical', ['user' => $proposal_data->uid])),
        $proposal_data->project_title,
        $approval_date,
        $actual_completion_date,
        $approval_status,
        ['data' => $edit_url],
      ];
    }
    if (empty($proposal_rows)) {
      \Drupal::messenger()->addStatus($this->t('There are no proposals.'));
    }
    $proposal_header = [
      $this->t('Date of Submission'),
      $this->t('Student Name'),
      $this->t('Title of the case-study project'),
      $this->t('Date of Approval'),
      $this->t('Date of Project Completion'),
      $this->t('Status'),
      $this->t('Action'),
    ];
    return [
      '#type' => 'table',
      '#header' => $proposal_header,
      '#rows' => $proposal_rows,
      '#empty' => $this->t('No proposals available.'),
      '#cache' => [
        'tags' => ['case_study_proposal_list'],
        'contexts' => ['user.permissions', 'url.path', 'url.query_args'],
      ],
    ];
  }



public function cfd_case_study_abstract() {
    $user = \Drupal::currentUser();
    $return_html = "";

    $proposal_data = cfd_case_study_get_proposal();
    if (!$proposal_data) {
        return [
          '#markup' => '',
          '#cache' => [
            'contexts' => ['user', 'url.path', 'url.query_args'],
          ],
        ];
    }

    /* Get experiment list */
    $connection = \Drupal::database();

    $query = $connection->select('case_study_submitted_abstracts', 'csa')
        ->fields('csa')
        ->condition('proposal_id', $proposal_data->id);
    $abstracts_q = $query->execute()->fetchObject();

    $query_pro = $connection->select('case_study_proposal', 'csp')
        ->fields('csp')
        ->condition('id', $proposal_data->id);
    $abstracts_pro = $query_pro->execute()->fetchObject();

    $query_pdf = $connection->select('case_study_submitted_abstracts_file', 'csaf')
        ->fields('csaf')
        ->condition('proposal_id', $proposal_data->id)
        ->condition('filetype', 'A');
    $abstracts_pdf = $query_pdf->execute()->fetchObject();

    $abstract_filename = "File not uploaded";
    if ($abstracts_pdf && !empty($abstracts_pdf->filename)) {
        $abstract_filename = $abstracts_pdf->filename;
    }

    $query_process = $connection->select('case_study_submitted_abstracts_file', 'csafp')
        ->fields('csafp')
        ->condition('proposal_id', $proposal_data->id)
        ->condition('filetype', 'S');
    $abstracts_query_process = $query_process->execute()->fetchObject();

    $abstracts_query_process_filename = "File not uploaded";
    if ($abstracts_query_process && !empty($abstracts_query_process->filename)) {
        $abstracts_query_process_filename = $abstracts_query_process->filename;
    }

    $url = "";
    if (!empty($abstracts_q)) {
        if (empty($abstracts_q->is_submitted)) {
            $url = Link::fromTextAndUrl('Upload Case Directory', Url::fromRoute('cfd_case_study.upload_abstract_code_form'))->toString();
        } elseif ($abstracts_q->is_submitted == 1) {
            $url = "";
        } elseif ($abstracts_q->is_submitted == 0) {
            $url = Link::fromTextAndUrl('Edit', Url::fromRoute('cfd_case_study.upload_abstract_code_form'))->toString();
        }
    }

    $return_html .= '<strong>Contributor Name:</strong><br />' . $proposal_data->name_title . ' ' . $proposal_data->contributor_name . '<br /><br />';
    $return_html .= '<strong>Title of the Case Study Project:</strong><br />' . $proposal_data->project_title . '<br /><br />';
    $return_html .= '<strong>Uploaded abstract of the project:</strong><br />' . $abstract_filename . '<br /><br />';
    $return_html .= '<strong>Uploaded Case Directory:</strong><br />' . $abstracts_query_process_filename . '<br /><br />';
    $return_html .= $url . '<br />';

    return [
      '#markup' => $return_html,
      '#cache' => [
        'tags' => [
          'case_study_proposal_list',
          'case_study_proposal:' . $proposal_data->id,
        ],
        'contexts' => ['user', 'url.path', 'url.query_args'],
      ],
    ];
}

  public function cfd_case_study_download_full_project() {
    $user = \Drupal::currentUser();
    $route_match = \Drupal::routeMatch();
    $id = (int) ($route_match->getParameter('id') ?? $route_match->getParameter('proposal_id') ?? \Drupal::request()->query->get('id'));
    $root_path = cfd_case_study_path();
    //var_dump($root_path);die;
    $query = \Drupal::database()->select('case_study_proposal');
    $query->fields('case_study_proposal');
    $query->condition('id', $id);
    $case_study_q = $query->execute();
    $case_study_data = $case_study_q->fetchObject();
    $CASE_STUDY_PATH = $case_study_data->directory_name . '/';
    /* zip filename */
    $zip_filename = $root_path . 'zip-' . time() . '-' . rand(0, 999999) . '.zip';
    /* creating zip archive on the server */
    $zip = new \ZipArchive();
    $zip->open($zip_filename, \ZipArchive::CREATE);
    $query = \Drupal::database()->select('case_study_submitted_abstracts_file');
    $query->fields('case_study_submitted_abstracts_file');
    $query->condition('proposal_id', $id);
    $project_files = $query->execute();
    while ($cfd_project_files = $project_files->fetchObject()) {
      $zip->addFile($root_path . $CASE_STUDY_PATH . $cfd_project_files->filepath, $CASE_STUDY_PATH . str_replace(' ', '_', basename($cfd_project_files->filename)));
    }
    $zip_file_count = $zip->numFiles;
    $zip->close();
    if ($zip_file_count > 0 && file_exists($zip_filename)) {
      $response = new BinaryFileResponse($zip_filename);
      $disposition = $response->headers->makeDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        str_replace(' ', '_', $case_study_data->project_title) . '.zip'
      );
      $response->headers->set('Content-Type', 'application/zip');
      $response->headers->set('Content-Disposition', $disposition);
      $response->deleteFileAfterSend(TRUE);
      return $response;
    }

    \Drupal::messenger()->addError("There are no case study project in this proposal to download");
    return new RedirectResponse(Url::fromUserInput('/case-study-project/full-download/project')->toString());
  }





  public function downloadFullProject($id) {
    $user = $this->currentUser();
    $database = \Drupal::database();
    $root_path = cfd_case_study_path();

    // Load proposal data
    $case_study_data = $database->select('case_study_proposal', 'c')
      ->fields('c')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$case_study_data) {
      $this->messenger()->addError("Invalid proposal ID.");
      return new RedirectResponse('/case-study-project/full-download/project');
    }

    $CASE_STUDY_PATH = $case_study_data->directory_name . '/';
    $zip_filename = $root_path . 'zip-' . time() . '-' . rand(0, 999999) . '.zip';

    $zip = new \ZipArchive();
    if ($zip->open($zip_filename, \ZipArchive::CREATE) !== TRUE) {
      $this->messenger()->addError("Could not create ZIP file.");
      return new RedirectResponse('/case-study-project/full-download/project');
    }

    // Get all project files for the proposal
    $project_files = $database->select('case_study_submitted_abstracts_file', 'f')
      ->fields('f')
      ->condition('proposal_id', $id)
      ->execute();

    while ($file = $project_files->fetchObject()) {
      $full_path = $root_path . $CASE_STUDY_PATH . $file->filepath;
      if (file_exists($full_path)) {
        $zip->addFile(
          $full_path,
          $CASE_STUDY_PATH . str_replace(' ', '_', basename($file->filename))
        );
      }
    }

    $zip_file_count = $zip->numFiles;
    $zip->close();

    if ($zip_file_count > 0 && file_exists($zip_filename)) {
      $response = new BinaryFileResponse($zip_filename);
      $disposition = $response->headers->makeDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        str_replace(' ', '_', $case_study_data->project_title) . '.zip'
      );
      $response->headers->set('Content-Type', 'application/zip');
      $response->headers->set('Content-Disposition', $disposition);

      // Delete the ZIP file after it's sent to the user
      $response->deleteFileAfterSend(true);

      return $response;
    }
    else {
      $this->messenger()->addError("There are no case study project files in this proposal to download.");
      return new RedirectResponse('/case-study-project/full-download/project');
    }
  }

public function cfd_case_study_completed_proposals_all() {
  $output = [];
  
  $query = \Drupal::database()->select('case_study_proposal', 'csp');
  $query->fields('csp');
  $query->condition('approval_status', 3);
  $query->orderBy('actual_completion_date', 'DESC');
  $result = $query->execute();
  $records = $result->fetchAll();

  if (empty($records)) {
    $output['description'] = [
      '#markup' => $this->t('Work has been completed for the following case studies. We welcome your contributions.') . '<hr>',
    ];
  } else {
    $rows = [];
    $counter = count($records);

    foreach ($records as $record) {
      $proposal_id = $record->id;
      $query_files = Database::getConnection()->select('case_study_submitted_abstracts_file', 'cssf')
        ->fields('cssf')
        ->condition('file_approval_status', 1)
        ->condition('proposal_id', $proposal_id);
      $case_study_abstract = $query_files->execute()->fetch();
      $year = date("Y", $record->actual_completion_date);
      $project_title = Link::fromTextAndUrl(
        $record->project_title,
        Url::fromRoute('cfd_case_study.run_form', ['id' => $record->id])
      )->toRenderable();

     

      $rows[] = [
        $counter,
        ['data' => $project_title],
        $record->contributor_name,
        $record->university,
        $year,
      ];
      $counter--;
    }

    $header = [
      $this->t('No'),
      $this->t('Case Study Project'),
      $this->t('Contributor Name'),
      $this->t('University/Institute'),
      $this->t('Year of Completion'),
    ];

    $output['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No completed proposals found.'),
      '#cache' => [
        'tags' => ['case_study_proposal_list'],
        'contexts' => ['user.permissions', 'url.path', 'url.query_args'],
      ],
    ];
  }

  $output['#cache'] = [
    'tags' => ['case_study_proposal_list'],
    'contexts' => ['user.permissions', 'url.path', 'url.query_args'],
  ];
  return $output;
}

  public function cfd_case_study_progress_all() {
    $page_content = [];
    $query = \Drupal::database()->select('case_study_proposal');
    $query->fields('case_study_proposal');
    $query->condition('approval_status', 1);
    $query->condition('is_completed', 0);
    $query->orderBy('approval_date', 'DESC');
    $result = $query->execute();
    $records = $result->fetchAll();
    if (count($records) == 0) {
      $page_content[] = [
        '#markup' => $this->t('Work is in progress for the following case studies under case study Project<hr>')
    ];
    } //$result->rowCount() == 0
    else {
      $page_content[] = [
        '#markup' => $this->t('Work is in progress for the following case studies under case study Project<hr>')
    ];
      
      $preference_rows = [];
      $i = count($records);

      foreach ($records as $row) {
          $completion_date = date("d-M-Y", $row->approval_date);
          $project_url = Link::fromTextAndUrl($row->project_title, Url::fromRoute('cfd_case_study.run_form'))->toString();
          $preference_rows[] = [
              $i,
              $row->project_title, 
              $row->contributor_name,
              $row->university,
              $completion_date,
          ];
          $i--;
      } //$row = $result->fetchObject()
      $preference_header = [
        'No',
        'Flowsheet Project',
        'Contributor Name',
        'University / Institute',
        'Year',
      ];
    
      $page_content =  [
        '#type' => 'table',
        '#header' => $preference_header,
        '#rows' => $preference_rows,
        '#cache' => [
          'tags' => ['case_study_proposal_list'],
          'contexts' => ['user.permissions', 'url.path', 'url.query_args'],
        ],
      ];
    }
    if (is_array($page_content)) {
      $page_content['#cache'] = [
        'tags' => ['case_study_proposal_list'],
        'contexts' => ['user.permissions', 'url.path', 'url.query_args'],
      ];
    }
    return $page_content;
  }
   
    

  public function list_of_available_project_titles() {
  $preference_rows = [];
  $i = 1;

  $connection = \Drupal::database();
  $query = $connection->query("
    SELECT * FROM list_of_project_titles 
    WHERE project_title_name NOT IN (
      SELECT project_title 
      FROM case_study_proposal 
      WHERE approval_status IN (0, 1, 3)
    )
  ");

  while ($result = $query->fetchObject()) {
    $url = Url::fromRoute('cfd_case_study.download_case_study_project_title_files', [], [
      'query' => ['id' => $result->id],
    ]);
    $link = Link::fromTextAndUrl($result->project_title_name, $url)->toRenderable();

    $preference_rows[] = [
      $i,
      ['data' => $link],
    ];
    $i++;
  }

  $preference_header = [
    t('No'),
    t('List of available projects'),
  ];

  // Return a render array for a Drupal table.
  return [
    '#type' => 'table',
    '#header' => $preference_header,
    '#rows' => $preference_rows,
    '#empty' => t('No available projects found.'),
    '#cache' => [
      'tags' => ['case_study_project_titles_list'],
      'contexts' => ['user.permissions', 'url.path', 'url.query_args'],
    ],
  ];
}

  public function download_case_study_project_title_files() {
    $route_match = \Drupal::routeMatch();
    $id = (int) ($route_match->getParameter('id') ?? $route_match->getParameter('proposal_id') ?? \Drupal::request()->query->get('id'));
    $root_path = cfd_case_study_project_titles_resource_file_path();
    $query = \Drupal::database()->select('list_of_project_titles');
    $query->fields('list_of_project_titles');
    $query->condition('id', $id);
    $result = $query->execute();
    $case_study_project_files_list = $result->fetchObject();
    $abstract_file = $case_study_project_files_list->filepath;
    $file_path = $root_path . $abstract_file;
    if (!is_file($file_path)) {
      throw new NotFoundHttpException();
    }
    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($abstract_file));
    return $response;
  }






  public function cfd_case_study_project_files(RouteMatchInterface $route_match) {
    // ✅ Replaces arg(3)
    $proposal_id = (int) $route_match->getParameter('proposal_id');

    $root_path = cfd_case_study_path();

    $query = \Drupal::database()->select('case_study_submitted_abstracts_file');
    $query->fields('case_study_submitted_abstracts_file');
    $query->condition('proposal_id', $proposal_id);
    $query->condition('filetype', 'A');
    $result = $query->execute();
    $cfd_case_study_project_files = $result->fetchObject();

    $query1 = \Drupal::database()->select('case_study_proposal');
    $query1->fields('case_study_proposal');
    $query1->condition('id', $proposal_id);
    $result1 = $query1->execute();
    $case_study = $result1->fetchObject();

    if (!$cfd_case_study_project_files || !$case_study) {
      throw new NotFoundHttpException();
    }

    $directory_name = $case_study->directory_name . '/';
    $abstract_file = $cfd_case_study_project_files->filename;
    $file_path = $root_path . $directory_name . $abstract_file;

    if (!file_exists($file_path)) {
      throw new NotFoundHttpException();
    }

    // ✅ DIRECT MIGRATION FOR DOWNLOAD RESPONSE (no logic change)
    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $abstract_file);

    return $response;
  }


  public function _list_case_study_certificates() {
    $user = $this->currentUser();
    $rows = [];

    $query = \Drupal::database()->select('case_study_proposal', 'csp')
      ->fields('csp', ['id', 'project_title', 'contributor_name'])
      ->condition('approval_status', 3)
      ->condition('uid', $user->id())
      ->orderBy('id', 'DESC');

    foreach ($query->execute() as $proposal) {
      $download_link = Link::fromTextAndUrl(
        $this->t('Download Certificate'),
        Url::fromRoute('cfd_case_study.generate_pdf', [], [
          'query' => ['id' => $proposal->id],
        ])
      )->toString();

      $rows[] = [
        $proposal->project_title,
        $proposal->contributor_name,
        ['data' => ['#markup' => $download_link]],
      ];
    }

    if (empty($rows)) {
      return [
        '#markup' => '<span style="color:red;">' . $this->t('No certificate available.') . '</span>',
        '#cache' => [
          'contexts' => ['user'],
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Project Title'),
        $this->t('Contributor Name'),
        $this->t('Download Certificates'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No certificate available.'),
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['case_study_proposal_list'],
      ],
    ];
  }

  public function verify_certificates($qr_code = NULL) {
    if ($qr_code === 'verify_certificates') {
      $qr_code = NULL;
    }
    $qr_code = $qr_code ?: \Drupal::request()->query->get('qr_code');
    if ($qr_code) {
      return [
        '#markup' => VerifyCertificatesForm::buildVerificationMarkup($qr_code),
      ];
    }

    return \Drupal::formBuilder()->getForm(VerifyCertificatesForm::class);
  }

  public function generatePdf() {
    $proposal_id = (int) (\Drupal::routeMatch()->getParameter('id') ?? \Drupal::request()->query->get('id') ?? \Drupal::request()->query->get('proposal_id'));
    if (!$proposal_id) {
      $this->messenger()->addError($this->t('Certificate is not available.'));
      return new RedirectResponse(Url::fromRoute('cfd_case_study._list_case_study_certificates')->toString());
    }

    $proposal = \Drupal::database()->select('case_study_proposal', 'csp')
      ->fields('csp')
      ->condition('approval_status', 3)
      ->condition('uid', $this->currentUser()->id())
      ->condition('id', $proposal_id)
      ->range(0, 1)
      ->execute()
      ->fetchObject();

    if (!$proposal) {
      $this->messenger()->addError($this->t('Certificate is not available.'));
      return new RedirectResponse(Url::fromRoute('cfd_case_study._list_case_study_certificates')->toString());
    }

    $module_relative_path = \Drupal::service('extension.list.module')->getPath('cfd_case_study');
    $module_path = DRUPAL_ROOT . '/' . $module_relative_path;
    require_once $module_path . '/pdf/fpdf/fpdf.php';
    require_once $module_path . '/pdf/phpqrcode/qrlib.php';

    $pdf = new \FPDF('L', 'mm', 'Letter');
    $pdf->AddPage();

    $image_bg = $module_path . '/pdf/images/bg_cert.png';
    if (is_file($image_bg)) {
      $pdf->Image($image_bg, 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());
    }

    $pdf->SetMargins(18, 1, 18);
    $pdf->Ln(30);
    $pdf->SetFont('Times', 'I', 18);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(320, 10, 'This is to certify that', '0', '1', 'C');
    $pdf->Ln(0);

    $full_name = trim(($proposal->name_title ?? '') . ' ' . ($proposal->contributor_name ?? ''));
    $pdf->SetTextColor(37, 22, 247);
    $pdf->Cell(320, 10, $full_name, '0', '1', 'C');
    $pdf->Ln(0);

    $project_title = wordwrap((string) $proposal->project_title, 60, "\n", TRUE);
    $university = 'from ' . ($proposal->university ?? '');

    $pdf->SetTextColor(0, 0, 0);
    $pdf->MultiCell(320, 10, $university, '0', 'C');
    $pdf->Ln(0);
    $pdf->Cell(320, 10, 'has successfully completed the case study on', '0', '1', 'C');
    $pdf->Ln(0);

    $pdf->SetTextColor(37, 22, 247);
    $pdf->SetFont('Times', 'I', 20);
    $pdf->MultiCell(320, 10, $project_title, '0', 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(0);
    $pdf->SetFont('Times', 'I', 18);
    $pdf->Cell(320, 8, 'using OpenFOAM.', '0', '1', 'C');
    $pdf->Ln(4);
    $pdf->Cell(320, 8, 'The work done is available at', '0', '1', 'C');
    $pdf->Cell(320, 4, '', '0', '1', 'C');
    $pdf->SetX(110);
    $pdf->SetFont('Times', 'I', 14);
    $pdf->SetTextColor(37, 22, 247);
    $project_url = Url::fromRoute('cfd_case_study.run_form', ['id' => $proposal_id], ['absolute' => TRUE])->toString();
    $pdf->write(0, $project_url, $project_url);
    $pdf->Ln(0);

    $qr_string = $this->getOrCreateCertificateQrCode($proposal_id);
    $qr_path = sys_get_temp_dir() . '/case-study-qr-' . uniqid('', TRUE) . '.png';
    $verify_url = Url::fromRoute('cfd_case_study.verify_certificates_verify_certificates', [], [
      'absolute' => TRUE,
      'query' => ['qr_code' => $qr_string],
    ])->toString();
    \QRcode::png($verify_url, $qr_path);

    $pdf->SetY(85);
    $pdf->SetX(320);
    $pdf->Ln(10);

    $sign1 = $module_path . '/pdf/images/sign1.png';
    $sign2 = $module_path . '/pdf/images/sign2.png';
    if (is_file($sign1)) {
      $pdf->Image($sign1, $pdf->GetX() + 75, $pdf->GetY() + 45, 60, 0);
    }
    if (is_file($sign2)) {
      $pdf->Image($sign2, $pdf->GetX() + 160, $pdf->GetY() + 45, 75, 0);
    }
    if (is_file($qr_path)) {
      $pdf->Image($qr_path, $pdf->GetX() + 15, $pdf->GetY() + 75, 30, 0);
    }

    $fossee = $module_path . '/pdf/images/fossee.png';
    $mhrd = $module_path . '/pdf/images/mhrd.png';
    $nia_logo = $module_path . '/pdf/images/nia.png';
    $ftr_line = $module_path . '/pdf/images/ftr_line.png';
    $ftr_line_2 = $module_path . '/pdf/images/ftr_line_2.png';

    $start_date = 1606761000;
    $end_date = 1640975364;
    $approval_date = (int) ($proposal->approval_date ?? 0);
    if ($approval_date > $start_date && $approval_date < $end_date) {
      if (is_file($fossee)) {
        $pdf->Image($fossee, $pdf->GetX() + 55, $pdf->GetY() + 80, 50, 0);
      }
      if (is_file($nia_logo)) {
        $pdf->Image($nia_logo, $pdf->GetX() + 135, $pdf->GetY() + 80, 50, 0);
      }
      if (is_file($mhrd)) {
        $pdf->Image($mhrd, $pdf->GetX() + 200, $pdf->GetY() + 80, 40, 0);
      }
      $pdf->Ln(2);
      if (is_file($ftr_line)) {
        $pdf->Image($ftr_line, $pdf->GetX() + 15, $pdf->GetY() + 105, 220, 0);
      }
      $pdf->Ln(3);
      if (is_file($ftr_line_2)) {
        $pdf->Image($ftr_line_2, $pdf->GetX() + 25, $pdf->GetY() + 106, 190, 0);
      }
    }
    else {
      if (is_file($fossee)) {
        $pdf->Image($fossee, $pdf->GetX() + 100, $pdf->GetY() + 80, 50, 0);
      }
      if (is_file($mhrd)) {
        $pdf->Image($mhrd, $pdf->GetX() + 180, $pdf->GetY() + 80, 40, 0);
      }
      $pdf->Ln(4);
      if (is_file($ftr_line)) {
        $pdf->Image($ftr_line, $pdf->GetX() + 15, $pdf->GetY() + 105, 220, 0);
      }
    }

    $pdf->SetFont('Times', 'I', 15);
    $pdf->SetLeftMargin(40);
    $pdf->Ln(62);
    $pdf->Cell(320, 8, $qr_string, '0', '1', 'L');
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(0, 0, 0);

    $filename = str_replace(' ', '-', (string) $proposal->contributor_name) . '-CFD-case-study-Certificate.pdf';
    $pdf_path = sys_get_temp_dir() . '/case-study-certificate-' . $proposal_id . '-' . uniqid('', TRUE) . '.pdf';
    $pdf->Output($pdf_path, 'F');

    if (is_file($qr_path)) {
      @unlink($qr_path);
    }

    $response = new BinaryFileResponse($pdf_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->headers->set('Content-Type', 'application/pdf');
    $response->deleteFileAfterSend(TRUE);
    return $response;
  }

  protected function getOrCreateCertificateQrCode($proposal_id) {
    $record = \Drupal::database()->select('case_study_qr_code', 'csq')
      ->fields('csq', ['qr_code'])
      ->condition('proposal_id', (int) $proposal_id)
      ->range(0, 1)
      ->execute()
      ->fetchObject();

    if ($record && !empty($record->qr_code) && $record->qr_code !== 'null') {
      return $record->qr_code;
    }

    $qr_code = $this->generateCertificateQrCode();

    if ($record) {
      \Drupal::database()->update('case_study_qr_code')
        ->fields(['qr_code' => $qr_code])
        ->condition('proposal_id', (int) $proposal_id)
        ->execute();
    }
    else {
      \Drupal::database()->insert('case_study_qr_code')
        ->fields([
          'proposal_id' => (int) $proposal_id,
          'qr_code' => $qr_code,
        ])
        ->execute();
    }

    return $qr_code;
  }

  protected function generateCertificateQrCode($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $characters_length = strlen($characters);
    $random_string = '';

    do {
      $random_string = '';
      for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[random_int(0, $characters_length - 1)];
      }

      $exists = (bool) \Drupal::database()->select('case_study_qr_code', 'csq')
        ->fields('csq', ['proposal_id'])
        ->condition('qr_code', $random_string)
        ->range(0, 1)
        ->execute()
        ->fetchField();
    } while ($exists);

    return $random_string;
  }

}
