<?php

declare(strict_types=1);

namespace Drupal\listing_access_demo\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Listings consult these grants, not hook_node_access(), so the two must agree.
 */
final class NodeGrantsHooks {

  /**
   * Bundles that everyone may view.
   */
  public const PUBLIC_BUNDLES = ['article'];

  /**
   * Bundles that only their author and staff may view.
   */
  public const PRIVATE_BUNDLES = ['secret_note', 'secret_invoice'];

  /**
   * The realm granting access to a node's author.
   */
  private const REALM_OWNER = 'demo_owner';

  /**
   * The realm granting access to staff.
   */
  private const REALM_STAFF = 'demo_staff';

  /**
   * Defines the grants stored for a node.
   */
  #[Hook('node_access_records')]
  public function records(NodeInterface $node): array {
    if (!$node->isPublished()) {
      return [];
    }
    if (in_array($node->bundle(), self::PUBLIC_BUNDLES, TRUE)) {
      return [self::grant('all', 0)];
    }
    if (in_array($node->bundle(), self::PRIVATE_BUNDLES, TRUE)) {
      return [
        self::grant(self::REALM_OWNER, (int) $node->getOwnerId()),
        self::grant(self::REALM_STAFF, 1),
      ];
    }
    return [];
  }

  /**
   * Defines the grants an account holds.
   */
  #[Hook('node_grants')]
  public function grants(AccountInterface $account, string $operation): array {
    if ($operation !== 'view') {
      return [];
    }
    $grants = [];
    if ($account->isAuthenticated()) {
      $grants[self::REALM_OWNER] = [(int) $account->id()];
    }
    if ($account->hasPermission('view any secret_note')) {
      $grants[self::REALM_STAFF] = [1];
    }
    return $grants;
  }

  /**
   * Builds a view-only grant record.
   */
  private static function grant(string $realm, int $gid): array {
    return [
      'realm' => $realm,
      'gid' => $gid,
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
    ];
  }

}
