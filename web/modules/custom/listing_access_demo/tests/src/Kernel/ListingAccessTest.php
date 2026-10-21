<?php

declare(strict_types=1);

namespace Drupal\Tests\listing_access_demo\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Tests that node listings respect ownership.
 */
final class ListingAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'listing_access_demo'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'secret_note', 'name' => 'Secret note'])->save();

    foreach (['anonymous', 'authenticated'] as $rid) {
      Role::create(['id' => $rid, 'label' => ucfirst($rid)])
        ->grantPermission('access content')
        ->save();
    }

    $admin = $this->createUser([], 'admin');
    $alice = $this->createUser([], 'alice');
    $bob = $this->createUser([], 'bob');
    $this->createUser(['view any secret_note'], 'staff');

    $nodes = [
      ['type' => 'secret_note', 'title' => 'Alice note 1', 'uid' => $alice->id()],
      ['type' => 'secret_note', 'title' => 'Alice note 2', 'uid' => $alice->id()],
      ['type' => 'secret_note', 'title' => 'Bob note', 'uid' => $bob->id()],
      ['type' => 'article', 'title' => 'Public article', 'uid' => $admin->id()],
    ];
    foreach ($nodes as $values) {
      Node::create($values + ['status' => 1])->save();
    }
  }

  /**
   * Tests that listings only contain nodes the account may view.
   */
  public function testListingsRespectOwnership(): void {
    $anonymous = new AnonymousUserSession();
    $alice = $this->userByName('alice');
    $bob = $this->userByName('bob');
    $staff = $this->userByName('staff');

    $this->assertSame(['Public article'], $this->listedTitles($anonymous));
    $this->assertSame(['Alice note 1', 'Alice note 2', 'Public article'], $this->listedTitles($alice));
    $this->assertSame(['Bob note', 'Public article'], $this->listedTitles($bob));
    $this->assertSame(['Alice note 1', 'Alice note 2', 'Bob note', 'Public article'], $this->listedTitles($staff));
  }

  /**
   * Loads a user by name.
   */
  private function userByName(string $name): AccountInterface {
    $users = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->loadByProperties(['name' => $name]);
    return reset($users);
  }

  /**
   * Returns the sorted titles of the nodes the account can view.
   */
  private function listedTitles(AccountInterface $account): array {
    $this->container->get('current_user')->setAccount($account);
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $nids = $storage->getQuery()->accessCheck(TRUE)->execute();
    $titles = array_map(static fn ($node) => $node->label(), $storage->loadMultiple($nids));
    sort($titles);
    return $titles;
  }

}
