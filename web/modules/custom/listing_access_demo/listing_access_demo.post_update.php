<?php

declare(strict_types=1);

/**
 * @file
 * Post update functions for the listing access demo module.
 */

/**
 * Implements hook_post_update_NAME().
 *
 * Rebuilds node access grants so existing content gets the grants defined by
 * hook_node_access_records(). Large sites should rebuild in batch mode.
 */
function listing_access_demo_post_update_rebuild_node_grants(array &$sandbox): string {
  \Drupal::service(\Drupal\node\NodeAccessRebuild::class)->rebuild();
  return 'Rebuilt node access grants.';
}

/**
 * Rebuilds node grants after secret_invoice became private.
 *
 * Existing nodes keep their old public grants until the rebuild runs.
 */
function listing_access_demo_post_update_classify_secret_invoice(array &$sandbox): string {
  \Drupal::service(\Drupal\node\NodeAccessRebuild::class)->rebuild();
  return 'Rebuilt node access grants after classifying secret_invoice.';
}
