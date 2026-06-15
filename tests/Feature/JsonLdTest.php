<?php

use FancySeo\JsonLd;

it('builds a HowTo node with ordered, positioned steps', function () {
    $node = JsonLd::howTo('Install Fancy CLI', [
        ['name' => 'Initialize', 'text' => 'Run npx fancy-ui init.'],
        ['name' => 'Add a component', 'text' => 'Run npx fancy-ui add card.', 'url' => 'https://example.test/docs/cli/add'],
    ], 'Vendor Fancy UI component source into your project.');

    expect($node['@context'])->toBe('https://schema.org')
        ->and($node['@type'])->toBe('HowTo')
        ->and($node['name'])->toBe('Install Fancy CLI')
        ->and($node['description'])->toBe('Vendor Fancy UI component source into your project.')
        ->and($node['step'])->toHaveCount(2)
        ->and($node['step'][0]['@type'])->toBe('HowToStep')
        ->and($node['step'][0]['position'])->toBe(1)
        ->and($node['step'][1]['position'])->toBe(2)
        ->and($node['step'][1]['url'])->toBe('https://example.test/docs/cli/add');
});

it('omits description when not given', function () {
    $node = JsonLd::howTo('Two steps', [
        ['name' => 'A', 'text' => 'do a'],
        ['name' => 'B', 'text' => 'do b'],
    ]);

    expect($node)->not->toHaveKey('description');
});
