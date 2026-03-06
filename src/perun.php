<?php

namespace Drupal\deims_routines;

use Drupal\node\NodeInterface;

class Perun {

  /**
   * React when the node name/title field changes.
   */
  public static function push_site_name_list() {

    // Example logic.
    \Drupal::logger('routines')->notice('A site name changed');

  }

}