<?php
// Temporary deploy-verification page. Safe to delete.
header('Content-Type: text/plain');
echo "apex-deploy-test OK\n";
echo "marker: AXC-DEPLOY-v2-b8e042\n";
echo "php: " . PHP_VERSION . "\n";
echo "server-time: " . date('c') . "\n";
