<?php

namespace Drupal\deims_routines\Controller;

use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use GuzzleHttp\ClientInterface;


class Perun extends ControllerBase {

  protected ClientInterface $httpClient;

  // Class-level constants (cannot reference $config here)
  private const BASE_URL = 'https://perun-api.aai.elter-ri.eu/ba/rpc/json/';
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
  
  public function getFormItems(): array {
	$url = self::BASE_URL . self::GET_FORM_ITEMS;

	$parameters = [
		'group' => self::GROUP_ID,
	];

	$response = $this->httpClient->request('POST', $url, [
		'json' => $parameters,
		'auth' => [$this->username, $this->password],
	]);

	return json_decode($response->getBody()->getContents(), TRUE);
  }

  /**
   * React when a site name changes — example method
   */
	public function pushSiteNameList(): void {

	  \Drupal::logger('routines')->notice('A site name changed');

	  // Initialize arrays
	  $site_titles = [];
	  $deimsids = [];

	  $nids = \Drupal::entityQuery('node')
		->condition('type', 'site')
		->accessCheck(FALSE)
		->execute();

	  $nodes = Node::loadMultiple($nids);

	  foreach ($nodes as $node) {
		if ($node instanceof NodeInterface) {

		  // Title
		  $site_titles[] = $node->getTitle();

		  // field_deims_id
		  if ($node->hasField('field_deims_id') && !$node->get('field_deims_id')->isEmpty()) {
			$deimsids[] = $node->get('field_deims_id')->value;
		  }
		}
	  }
	  

	  try {
		$form_items = $this->getFormItems();

		\Drupal::logger('routines')->info('Form items received: @items', [
		  '@items' => json_encode($form_items, JSON_PRETTY_PRINT),
		]);

	  } catch (\GuzzleHttp\Exception\GuzzleException $e) {

		\Drupal::logger('routines')->error('Perun API request failed (Guzzle): @message', [
		  '@message' => $e->getMessage(),
		]);

	  } catch (\Exception $e) {

		\Drupal::logger('routines')->error('Unexpected error in Perun API: @message', [
		  '@message' => $e->getMessage(),
		]);

	  }

	  // Debug output
	  
	  \Drupal::logger('routines')->info('Titles: @titles', [
		'@titles' => implode(', ', $site_titles)
	  ]);

	  \Drupal::logger('routines')->info('DEIMS.IDs: @deimsids', [
		'@deimsids' => implode(', ', $deimsids)
	  ]);
	}

}
