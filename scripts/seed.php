<?php

declare(strict_types=1);

/**
 * @file
 * Seeds the listing access demo content.
 *
 * Idempotent: safe to run repeatedly.
 *
 * Usage: ddev drush php:script scripts/seed.php
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

$user_storage = \Drupal::entityTypeManager()->getStorage('user');
$users = [];
foreach (['alice', 'bob'] as $name) {
  $matches = $user_storage->loadByProperties(['name' => $name]);
  $user = $matches ? reset($matches) : NULL;
  if ($user === NULL) {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.com',
      'status' => 1,
      'pass' => \Drupal::service('password_generator')->generate(),
    ]);
    $user->save();
  }
  $users[$name] = $user;
}

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$matches = $term_storage->loadByProperties(['vid' => 'tags', 'name' => 'billing']);
$term = $matches ? reset($matches) : NULL;
if ($term === NULL) {
  $term = Term::create([
    'vid' => 'tags',
    'name' => 'billing',
  ]);
  $term->save();
}

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$nids = [];
foreach (['Secret note ', 'Public article '] as $prefix) {
  $nids = array_merge($nids, $node_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('title', $prefix . '%', 'LIKE')
    ->execute());
}
foreach ($node_storage->loadMultiple(array_unique($nids)) as $stale) {
  $stale->delete();
}

$nodes = [
  [
    'type' => 'secret_note',
    'title' => "Secret note A: alice's card ends 4242",
    'uid' => (int) $users['alice']->id(),
    'promote' => 1,
    'body_text' => "Alice's card ending 4242 pays for the quasar plan.",
  ],
  [
    'type' => 'secret_note',
    'title' => "Secret note B: alice's home address",
    'uid' => (int) $users['alice']->id(),
    'promote' => 1,
    'body_text' => "Alice lives at quasar street 42.",
  ],
  [
    'type' => 'secret_note',
    'title' => "Secret note C: alice's vet records",
    'uid' => (int) $users['alice']->id(),
    'body_text' => "The vet says quasar the cat is fine.",
  ],
  [
    'type' => 'article',
    'title' => 'Public article 1: how billing works',
    'uid' => 1,
    'promote' => 1,
    'body_text' => 'Billing runs on the quasar plan.',
  ],
  [
    'type' => 'article',
    'title' => 'Public article 2: pricing',
    'uid' => 1,
    'promote' => 1,
    'body_text' => 'Pricing for the quasar plan starts here.',
  ],
];

$created = [];
foreach ($nodes as $data) {
  $values = [
    'type' => $data['type'],
    'title' => $data['title'],
    'uid' => $data['uid'],
    'status' => 1,
    'body' => [
      'value' => $data['body_text'],
      'format' => 'basic_html',
    ],
    'field_tags' => [$term->id()],
  ];
  if (isset($data['promote'])) {
    $values['promote'] = $data['promote'];
  }
  $node = Node::create($values);
  $node->save();
  $created[] = $node;
}

\Drupal::service('cron')->run();

foreach ($created as $node) {
  echo $node->id() . ' ' . $node->bundle() . ' ' . $node->label() . PHP_EOL;
}
echo 'term billing = ' . $term->id() . PHP_EOL;
