<?php

namespace Drupal\cfd_case_study\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class VerifyCertificatesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cfd_case_study_verify_certificates_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['qr_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter QR Code'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('qr_code', ''),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Verify'),
      '#ajax' => [
        'callback' => '::submitAjax',
        'progress' => [
          'message' => '',
        ],
      ],
    ];

    $form['displaytable'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'displaytable'],
      'content' => [
        '#markup' => $form_state->get('verification_markup') ?? '',
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback for certificate verification.
   */
  public function submitAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#displaytable', self::buildVerificationMarkup($form_state->getValue('qr_code'))));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->set('verification_markup', self::buildVerificationMarkup($form_state->getValue('qr_code')));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Builds certificate verification markup for a QR code.
   */
  public static function buildVerificationMarkup($qr_code) {
    $qr_code = trim((string) $qr_code);
    if ($qr_code === '') {
      return '';
    }

    $translator = \Drupal::translation();
    $proposal_id = \Drupal::database()->select('case_study_qr_code', 'csq')
      ->fields('csq', ['proposal_id'])
      ->condition('qr_code', $qr_code)
      ->execute()
      ->fetchField();

    if (!$proposal_id) {
      return '<strong>' . Html::escape((string) $translator->translate('Sorry! The serial number you entered seems to be invalid. Please try again!')) . '</strong>';
    }

    $proposal = \Drupal::database()->select('case_study_proposal', 'csp')
      ->fields('csp', ['contributor_name', 'project_title', 'project_guide_name'])
      ->condition('approval_status', 3)
      ->condition('id', (int) $proposal_id)
      ->execute()
      ->fetchObject();

    if (!$proposal) {
      return '<strong>' . Html::escape((string) $translator->translate('Certificate details were not found for this QR code.')) . '</strong>';
    }

    $rows = [
      [$translator->translate('Name'), $proposal->contributor_name],
      [$translator->translate('Project'), $translator->translate('Case Study Project')],
      [$translator->translate('Case Study completed'), $proposal->project_title],
    ];

    if (!empty($proposal->project_guide_name)) {
      $rows[] = [$translator->translate('Project Guide'), $proposal->project_guide_name];
    }

    $table_rows = '';
    foreach ($rows as [$label, $value]) {
      $table_rows .= '<tr><td>' . Html::escape((string) $label) . '</td><td>' . Html::escape((string) $value) . '</td></tr>';
    }

    return '<h4>' . Html::escape((string) $translator->translate('Participation Details')) . '</h4><table>' . $table_rows . '</table>';
  }

}
