<?php

use FancySeo\Tests\HostTestCase;
use FancySeo\Tests\TestCase;

uses(TestCase::class)->in('Feature');

// A host whose routes load after the package boots (routes/web.php).
uses(HostTestCase::class)->in('Host');
