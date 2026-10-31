<?php

declare(strict_types=1);

// ok so the server resolves its upgrade schemas and bedrock data through its own vendor dir, which doesn't
// exist when it's installed as a dependency. Point it at ours.
$serverVendor = __DIR__ . '/../vendor/altayofficial/altay/vendor';
if(!file_exists($serverVendor)){
	symlink('../../', $serverVendor);
}

require __DIR__ . '/../vendor/autoload.php';
