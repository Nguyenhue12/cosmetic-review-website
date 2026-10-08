<?php
$dir = new RecursiveDirectoryIterator('.');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/.*\.php$/', RegexIterator::GET_MATCH);
$count = 0;
foreach($files as $file) {
    if(strpos($file[0], 'vendor') !== false) continue;
    $content = file_get_contents($file[0]);
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($content, Normalizer::FORM_C);
        if ($normalized !== $content && $normalized !== false) {
            file_put_contents($file[0], $normalized);
            $count++;
            echo 'Normalized: ' . $file[0] . "\n";
        }
    } else {
        echo 'Intl extension not loaded!'; break;
    }
}
echo 'Total fixed: ' . $count;
