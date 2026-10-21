<?php

declare(strict_types=1);

namespace Drupal\listing_access_demo\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Node access hooks for the listing access demo.
 */
final class NodeAccessHooks {

  /**
   * Hides secret notes from everyone but their author and staff.
   */
  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, string $operation, AccountInterface $account): AccessResultInterface {
    if (!in_array($node->bundle(), ['secret_note', 'secret_invoice'], TRUE) || $operation !== 'view') {
      return AccessResult::neutral();
    }
    $is_author = $account->isAuthenticated() && (int) $node->getOwnerId() === (int) $account->id();
    if ($is_author || $account->hasPermission('view any secret_note')) {
      return AccessResult::neutral()->cachePerUser()->addCacheableDependency($node);
    }
    return AccessResult::forbidden('Secret notes are visible to their author and staff only.')
      ->cachePerUser()
      ->addCacheableDependency($node);
  }

}
