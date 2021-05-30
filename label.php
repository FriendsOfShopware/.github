<?php

use Github\Client;

require __DIR__ . '/vendor/autoload.php';

$client = new \Github\Client();
$client->authenticate($_SERVER['GITHUB_TOKEN'], null, Client::AUTH_ACCESS_TOKEN);
$pager = new \Github\ResultPager($client);

$repos = $pager->fetchAll($client->organization(), 'repositories', ['FriendsOfShopware', 'public']);
$orgLabels = json_decode(file_get_contents(__DIR__ . '/labels.json'), true);

foreach ($repos as $repo) {
    if ($repo['archived']) {
        continue;
    }

    $labels = $client->repo()->labels()->all('FriendsOfShopware', $repo['name']);

    // Update labels if similar
    foreach ($labels as &$label) {
        foreach ($orgLabels as $labelName => $config) {
            if (str_contains($labelName, $label['name'])) {
                $client->repo()->labels()->update('FriendsOfShopware', $repo['name'], $label['name'], array_merge(['name' => $labelName], $config));
                $label['name'] = $labelName;
            }
        }
    }

    unset($label);

    // Delete obsolete labels
    foreach ($labels as $label) {
        if (!isset($orgLabels[$label['name']])) {
            $client->repo()->labels()->remove('FriendsOfShopware', $repo['name'], $label['name']);
        } elseif($orgLabels[$label['name']]['color'] !== $label['color']) {
            $client->repo()->labels()->update('FriendsOfShopware', $repo['name'], $label['name'], $orgLabels[$label['name']]);
        }
    }

    // Add new labels
    $existingLabels = array_column($labels, 'name');
    foreach ($orgLabels as $labelName => $config) {
        if (!in_array($labelName, $existingLabels, true)) {
            $client->repo()->labels()->create('FriendsOfShopware', $repo['name'], array_merge(['name' => $labelName], $config));
        }
    }

    echo '=> Finished ' . $repo['name'] . PHP_EOL;
}