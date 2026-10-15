<?php

use Illuminate\Contracts\Console\Kernel;

/*
 | Compile CHAQUE vue Blade et signale celles qui ne passent pas.
 |
 | Le defaut trouve sur `cours.blade.php` ne se voit pas a la lecture : le
 | fichier parait intact, et c'est Blade qui echoue plus loin. Une verification
 | de compilation attrape la classe entiere de ce probleme, pas seulement ce
 | cas-la.
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fichiers = [];
$iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views'));
foreach ($iterateur as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
        $fichiers[] = $f->getPathname();
    }
}
sort($fichiers);

$casses = 0;
foreach ($fichiers as $vue) {
    $compile = app('blade.compiler')->compileString(file_get_contents($vue));
    $tmp = sys_get_temp_dir().'/vf-verif.php';
    file_put_contents($tmp, $compile);
    $sortie = (string) shell_exec('php -l '.escapeshellarg($tmp).' 2>&1');
    @unlink($tmp);
    if (! str_contains($sortie, 'No syntax errors')) {
        $casses++;
        echo 'CASSEE : '.$vue."\n   ".trim(explode("\n", $sortie)[0] ?? '')."\n";
    }
}

echo "\nvues examinees : ".count($fichiers).' | cassees : '.$casses."\n";
exit($casses === 0 ? 0 : 1);
