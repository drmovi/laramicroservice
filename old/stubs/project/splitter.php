<?php

$microserviceName = $argv[1] ?? null;
if (!$microserviceName) {
    echo 'Microservice name is not provided';
    exit(1);
}

//if(!is_dir(__DIR__ . '/microservices/' . $microserviceName)) {
//    echo 'Microservice directory does not exists';
//    exit(1);
//}

$microservices = array_map('basename', glob(__DIR__ . '/microservices/*', GLOB_ONLYDIR));
$microservicesData = [];
foreach ($microservices as $microservice) {
    $composerJson = json_decode(file_get_contents(__DIR__ . '/microservices/' . $microservice . '/composer.json'), true);
    $microservicesData[$microservice] = $composerJson['name'];
}

$filterList = [$microserviceName, 'shared'];
$otherMicroservices = array_filter($microservicesData, function ($key) use ($filterList) {
    return !in_array($key, $filterList);
}, ARRAY_FILTER_USE_KEY);

foreach ($otherMicroservices as $name => $composerName) {
    exec("composer remove $composerName --no-interaction");
    exec("composer config repositories.$name --unset --no-interaction");
    exec("rm -rf ./microservices/$name");
}

exit(0);
