<?php

/**
 * @file
 * Contains \Drupal\cfd_case_study\Form\CfdCaseStudyRunForm.
 */

namespace Drupal\cfd_case_study\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

class CfdCaseStudyRunForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cfd_case_study_run_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $options = $this->getCaseStudyOptions();
    $selected = $this->resolveSelectedCaseStudyId($form_state, $options);
    $details_markup = $selected ? $this->buildCaseStudyDetailsMarkup($selected) : '';
    $download_links_markup = $selected ? $this->buildDownloadLinksMarkup($selected) : '';

    $form['case_study'] = [
      '#type' => 'select',
      '#title' => $this->t('Title of the case study'),
      '#options' => $options,
      '#default_value' => $selected,
      '#ajax' => [
        'callback' => '::caseStudyProjectDetailsCallback',
        'event' => 'change',
        'limit_validation_errors' => [['case_study']],
        'wrapper' => 'ajax_case_study_wrapper',
      ],
    ];

    $form['case_study_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax_case_study_wrapper'],
    ];
    $form['case_study_wrapper']['case_study_details'] = [
      '#type' => 'item',
      '#markup' => '<div id="ajax_case_study_details">' . $details_markup . '</div>',
    ];
    $form['case_study_wrapper']['selected_case_study'] = [
      '#type' => 'item',
      '#markup' => '<div id="ajax_selected_case_study">' . $download_links_markup . '</div>',
    ];

    return $form;
  }

  public function caseStudyProjectDetailsCallback(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['case_study_wrapper'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Resolves the active case study from form state, route, or query string.
   */
  protected function resolveSelectedCaseStudyId(FormStateInterface $form_state, array $options) {
    $selected = $form_state->getValue('case_study');

    if ($selected === NULL || $selected === '') {
      $route_match = \Drupal::routeMatch();
      $selected = (int) ($route_match->getParameter('id') ?? \Drupal::request()->query->get('id') ?? key($options));
    }

    $selected = (int) $selected;

    if ($selected === 0) {
      return 0;
    }

    return $this->loadCaseStudyInformation($selected) ? $selected : 0;
  }

  /**
   * Builds the download links shown for a selected case study.
   */
  protected function buildDownloadLinksMarkup($case_study_id) {
    $abstract_link = Link::fromTextAndUrl(
      $this->t('Download Abstract'),
      Url::fromRoute('cfd_case_study.project_files', ['proposal_id' => $case_study_id])
    )->toString();

    $full_project_link = Link::fromTextAndUrl(
      $this->t('Download Case Study'),
      Url::fromRoute('cfd_case_study.download_full_project', [], [
        'query' => ['id' => $case_study_id],
      ])
    )->toString();

    return $abstract_link . '<br>' . $full_project_link;
  }

  /**
   * Returns the selectable list of completed case studies.
   */
  protected function getCaseStudyOptions() {
    $case_study_titles = [
      0 => $this->t('Please select...'),
    ];

    $query = \Drupal::database()->select('case_study_proposal', 'csp')
      ->fields('csp', ['id', 'project_title', 'name_title', 'contributor_name'])
      ->condition('approval_status', 3)
      ->orderBy('project_title', 'ASC');

    foreach ($query->execute() as $case_study) {
      $case_study_titles[$case_study->id] = $case_study->project_title . ' (Proposed by ' . trim($case_study->name_title . ' ' . $case_study->contributor_name) . ')';
    }

    return $case_study_titles;
  }

  /**
   * Loads a completed case study proposal by ID.
   */
  protected function loadCaseStudyInformation($proposal_id) {
    if (empty($proposal_id)) {
      return NULL;
    }

    return \Drupal::database()->select('case_study_proposal', 'csp')
      ->fields('csp')
      ->condition('id', (int) $proposal_id)
      ->condition('approval_status', 3)
      ->execute()
      ->fetchObject() ?: NULL;
  }

  /**
   * Builds the case study details markup shown below the selector.
   */
  protected function buildCaseStudyDetailsMarkup($case_study_id) {
    $case_study = $this->loadCaseStudyInformation($case_study_id);
    if (!$case_study) {
      return '';
    }

    return '<span style="color: rgb(128, 0, 0);"><strong>' . $this->t('About the case study') . '</strong></span><br />'
      . '<ul>'
      . '<li><strong>' . $this->t('Proposer Name:') . '</strong> ' . Html::escape(trim($case_study->name_title . ' ' . $case_study->contributor_name)) . '</li>'
      . '<li><strong>' . $this->t('Title of the Case Study:') . '</strong> ' . Html::escape($case_study->project_title) . '</li>'
      . '<li><strong>' . $this->t('University:') . '</strong> ' . Html::escape($case_study->university) . '</li>'
      . '</ul>';
  }
}
?>
