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

  private const BASE_URL = 'https://perun-api.aai.elter-ri.eu/ba/rpc/json/';
  private const GET_FORM_ITEMS = 'registrarManager/getFormItems';
  private const UPDATE_FORM_ITEMS = 'registrarManager/updateFormItems';
  private const GROUP_ID = 3;
  private const DEIMS_SITES_SHORTNAME = 'DEIMS_sites';

  private string $username;
  private string $password;

  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
    $config = \Drupal::service('settings')->get('deims_routines');
    $this->username = $config['username'] ?? '';
    $this->password = $config['password'] ?? '';
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * Fetches all form items for the group from Perun.
   */
  public function getFormItems(): array {
    $response = $this->httpClient->request('POST', self::BASE_URL . self::GET_FORM_ITEMS, [
      'json' => ['group' => self::GROUP_ID],
      'auth' => [$this->username, $this->password],
    ]);
    return json_decode($response->getBody()->getContents(), TRUE);
  }

  /**
   * Finds a single form item by its shortname.
   */
  public function getFormItemByShortname(array $form_items, string $shortname): ?array {
    foreach ($form_items as $item) {
      if ($item['shortname'] === $shortname) {
        return $item;
      }
    }
    return NULL;
  }

  /**
   * Sends the full (modified) form items array back to Perun.
   */
  public function updateFormItems(array $items): int {
    $response = $this->httpClient->request('POST', self::BASE_URL . self::UPDATE_FORM_ITEMS, [
      'json' => [
        'group' => self::GROUP_ID,
        'items' => $items,
      ],
      'auth' => [$this->username, $this->password],
    ]);
    return (int) json_decode($response->getBody()->getContents(), TRUE);
  }

  /**
   * Builds the pipe-separated options string for the DEIMS_sites combobox
   * from current active DEIMS.ID nodes: "{uuid}#{Title}|{uuid}#{Title}|..."
   * Excludes sites with status tid 54180 (inactive/closed), but includes
   * sites where field_status is not set.
   */
  private function buildDeimsSitesOptions(): string {
    $options = [];

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'site')
      ->accessCheck(FALSE);

    $exclude_closed = $query->orConditionGroup()
      ->condition('field_status.entity:taxonomy_term.tid', 54180, '!=')
      ->condition('field_status', NULL, 'IS NULL');

    $query->condition($exclude_closed);

    $nids = $query->execute();
    $nodes = Node::loadMultiple($nids);

    foreach ($nodes as $node) {
      if (!($node instanceof NodeInterface)) {
        continue;
      }
      if ($node->hasField('field_deims_id') && !$node->get('field_deims_id')->isEmpty()) {
        $uuid = $node->get('field_deims_id')->value;
        $title = $node->getTitle();
        $options[] = $uuid . '#' . $title;
      }
    }

    return implode('|', $options);
  }

  /**
   * Reacts when a site name changes — updates the DEIMS_sites combobox in Perun.
   */
  public function pushSiteNameList(): void {
    \Drupal::logger('deims_routines')->notice('pushSiteNameList to PERUN triggered');

    try {
      // 1. Fetch current form items
      $form_items = $this->getFormItems();

      // 2. Find the DEIMS_sites item and update its options string
      $updated = FALSE;
      foreach ($form_items as &$item) {
        if ($item['shortname'] === self::DEIMS_SITES_SHORTNAME) {
          $new_options = $this->buildDeimsSitesOptions();
          $item['i18n']['en']['options'] = $new_options;
          \Drupal::logger('deims_routines')->info('Updated DEIMS_sites options: @opts', [
            '@opts' => $new_options,
          ]);
          $updated = TRUE;
          break;
        }
      }
      unset($item); // always unset after foreach by reference

      if (!$updated) {
        \Drupal::logger('deims_routines')->warning('DEIMS_sites form item not found — nothing updated');
        return;
      }

      // 3. Push the full modified array back to Perun
      $result = $this->updateFormItems($form_items);
      if ($result === 12) {
        \Drupal::logger('deims_routines')->info('Perun DEIMS_sites updated successfully (@count items).', [
          '@count' => $result,
        ]);
      }
      else {
        \Drupal::logger('deims_routines')->warning('Perun updateFormItems returned unexpected value: @resp', [
          '@resp' => $result,
        ]);
      }

    } catch (\GuzzleHttp\Exception\GuzzleException $e) {
      \Drupal::logger('deims_routines')->error('Perun API request failed (Guzzle): @message', [
        '@message' => $e->getMessage(),
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('deims_routines')->error('Unexpected error in Perun API: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }
}
