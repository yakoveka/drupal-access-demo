<?php

declare(strict_types=1);

namespace Drupal\Tests\listing_access_demo\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\listing_access_demo\Hook\NodeGrantsHooks;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that every node type is classified by the grants policy.
 */
final class BundleClassificationTest extends UnitTestCase {

  /**
   * Tests that every node type is public or private in NodeGrantsHooks.
   */
  public function testEveryNodeTypeIsClassified(): void {
    $config_dir = dirname(__DIR__, 7) . '/config/sync';
    $this->assertDirectoryExists($config_dir, "Config sync directory '$config_dir' does not exist.");

    $bundles = [];
    foreach (glob($config_dir . '/node.type.*.yml') as $file) {
      $config = Yaml::decode(file_get_contents($file));
      $bundles[] = $config['type'];
    }

    $this->assertNotEmpty($bundles, 'No node type config files were found in config/sync.');

    $classified = [...NodeGrantsHooks::PUBLIC_BUNDLES, ...NodeGrantsHooks::PRIVATE_BUNDLES];
    foreach ($bundles as $bundle) {
      $this->assertTrue(
        in_array($bundle, $classified, TRUE),
        "Node type '$bundle' is not classified as public or private in NodeGrantsHooks; when published, core makes it public in every listing.",
      );
    }

    $this->assertEmpty(
      array_intersect(NodeGrantsHooks::PUBLIC_BUNDLES, NodeGrantsHooks::PRIVATE_BUNDLES),
      'A node type cannot be both public and private in NodeGrantsHooks.',
    );
  }

}
