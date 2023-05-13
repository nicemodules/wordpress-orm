<?php
/**
 * Initialization of WordPress environment and / or composer autoload, for phpunit tests.
 * Printing basic information.
 */

namespace NiceModules;

$wpLoad = 'your_path_to/wp-load.php';
$vendorLoad = 'your_path_to/vendor/autoload.php';

require_once($wpLoad);

print_r('Bootstrap include: ' . $wpLoad . PHP_EOL);

// if loaded with WordPress wp-load.php, no need to include composer autoload  
if (!class_exists('NiceModules\ORM\Mapper')) {
    require_once($vendorLoad);
    print_r('Bootstrap include: ' . $vendorLoad . PHP_EOL);
}

print_r('PHP version: ' . phpversion() . PHP_EOL);
print_r('WordPress Version: ' . get_bloginfo('version') . PHP_EOL);
