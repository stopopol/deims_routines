<?php

namespace Drupal\deims_routines;

use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use GuzzleHttp\ClientInterface;

// implements MUNI's PERUN https://perun-aai.org/
class Perun extends ControllerBase {

  protected ClientInterface $httpClient;

  private const BASE_URL = 'https://perun-api.elter-ri.eu/ba/rpc/json/';
  private const GET_FORM_ITEMS = 'registrarManager/getFormItems';
  private const UPDATE_FORM_ITEMS = 'registrarManager/updateFormItems';
  private const GROUP_ID = '3';

  // Runtime properties for credentials
  private string $username;
  private string $password;

  /**
   * Constructor — inject HTTP client and load credentials from settings.php
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;

    // Load credentials from settings.php
    $config = \Drupal::service('settings')->get('deims_routines');
    $this->username = $config['username'] ?? '';
    $this->password = $config['password'] ?? '';
  }

  /**
   * Drupal service container creation
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * React when a site name changes — example method
   */
  public function pushSiteNameList(): void {

    // Test if function is called
    \Drupal::logger('deims_routines')->notice('A site name changed');

    // Arrays to store the results
    $site_names = [];
    $countries = [];

    // Load all node IDs of type 'site'
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'site')
	  ->accessCheck(FALSE)
      ->execute();

    // Load the node entities in batch
    $nodes = Node::loadMultiple($nids);

    foreach ($nodes as $node) {
		if ($node->hasField('field_name') && !$node->get('field_name')->isEmpty()) {
			$site_names[] = $node->get('field_name')->value;
		}

		// --- country field (multi-value, list-text) ---
		if ($node->hasField('field_country') && !$node->get('field_country')->isEmpty()) {

			// Get allowed values mapping key => label
			$field_def = $node->get('field_country')->getFieldDefinition();
			$allowed_values = $field_def->getSetting('allowed_values') ?: [];

			// Iterate over all values
			foreach ($node->get('field_country') as $item) {
				$key = $item->value;
				$label = $allowed_values[$key] ?? $key;
				$countries[] = $label;
			}
		}

    }

    // Debug output
    \Drupal::logger('deims_routines')->info('Site names: @names', ['@names' => implode(', ', $site_names)]);
    \Drupal::logger('deims_routines')->info('Countries: @countries', ['@countries' => implode(', ', $countries)]);

  }

}

