<?php

namespace Drupal\deims_routines\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filter nodes by the number of field_parameters taxonomy term references.
 *
 * @ViewsFilter("parameter_count_filter")
 */
class ParameterCountFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function adminLabel($short = FALSE) {
    return $this->t('Parameter count filter');
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['operator'] = ['default' => 'gt'];
    $options['value'] = ['default' => 5];
    $options['only_bottom_level_terms'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function operatorOptions() {
    return [
      'gt'  => $this->t('Is greater than'),
      'gte' => $this->t('Is greater than or equal to'),
      'lt'  => $this->t('Is less than'),
      'lte' => $this->t('Is less than or equal to'),
      'eq'  => $this->t('Is equal to'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['operator'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Operator'),
      '#options'       => $this->operatorOptions(),
      '#default_value' => $this->options['operator'],
    ];

    $form['value'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Number of parameter values'),
      '#default_value' => $this->options['value'],
      '#min'           => 0,
      '#step'          => 1,
    ];

    $form['only_bottom_level_terms'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Only count child-level terms (no parent)'),
      '#description'   => $this->t('When enabled, only taxonomy terms without a parent term are counted.'),
      '#default_value' => $this->options['only_bottom_level_terms'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);

    $form['operator'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Operator'),
      '#options'       => $this->operatorOptions(),
      '#default_value' => $this->options['operator'],
    ];

    $form['value'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Number of parameter values'),
      '#default_value' => $this->options['value'],
      '#min'           => 0,
      '#step'          => 1,
    ];

    $form['only_bottom_level_terms'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Only count top-level terms (no parent)'),
      '#description'   => $this->t('When enabled, only taxonomy terms without a parent term are counted.'),
      '#default_value' => $this->options['only_bottom_level_terms'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $field_table = 'node__field_parameters';
    $count       = (int) $this->options['value'];
    $having_op   = $this->havingOperator($this->options['operator']);

    $subquery = \Drupal::database()->select($field_table, 'fp')
      ->fields('fp', ['entity_id'])
      ->condition('fp.deleted', 0)
      ->groupBy('fp.entity_id')
      ->having("COUNT(fp.entity_id) $having_op $count");

    // When the option is enabled, restrict to terms that have no parent
    // (i.e. parent_target_id = 0 in taxonomy_term__parent).
    if (!empty($this->options['only_bottom_level_terms'])) {
      $subquery->join(
        'taxonomy_term__parent',
        'ttp',
        'ttp.entity_id = fp.field_parameters_target_id'
      );
      $subquery->condition('ttp.parent_target_id', 0);
    }

    $this->query->addWhere(
      $this->options['group'],
      'node_field_data.nid',
      $subquery,
      'IN'
    );
  }

  /**
   * Maps option operator keys to SQL comparison operators.
   */
  protected function havingOperator(string $key): string {
    $map = [
      'gt'  => '>',
      'gte' => '>=',
      'lt'  => '<',
      'lte' => '<=',
      'eq'  => '=',
    ];
    return $map[$key] ?? '>';
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return TRUE;
  }

}
