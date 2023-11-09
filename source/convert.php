<?php

namespace App;

use Symfony\Component\Yaml\Yaml;

require_once 'vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

$data = Yaml::parseFile(__DIR__ . '/assets/characters.yaml');

$characters = [];

foreach ($data['characters'] as $charId => $charData) {
    $charData['id'] = $charId;
    $tag = $charData['tag'] ?? 'UNTAGGED';
    $sources = [];

    foreach ($charData['sources'] as $sourceId => $sourceData) {
        if (!is_array($sourceData)) {
            $sourceData = [$sourceData];
        }

        foreach ($sourceData as $idx => $sourceDatum) {
            $imageFilename = (count($sourceData) > 1)
                ? sprintf('%s_%s_%d', $charId, $sourceId, $idx)
                : sprintf('%s_%s', $charId, $sourceId);

            $imagePath = null;

            $tryImagePaths = [__DIR__ . '/assets/images/chars/' . $imageFilename . '.png',
                __DIR__ . '/assets/images/chars/' . $imageFilename . '.webp'];
            foreach ($tryImagePaths as $tryImagePath) {
                if (is_file($tryImagePath)) {
                    $imagePath = $tryImagePath;
                    break;
                }
            }

            if ($imagePath === null || !is_file($imagePath)) {
                //echo ($imageFilename . ' not found.' );
                echo sprintf('%s.png not found (p.%s, %s).', $imageFilename, $sourceDatum, $charData['ids']), "\n";
                //var_dump($sourceDatum);

                continue;
            }

            if (is_string($sourceDatum)) {
                $sourceDatum = ['location' => $sourceDatum];
            }

            $evidencePath = null;
            foreach (['jpg', 'png'] as $tryExtension) {
                $ext = $data['sources'][$sourceId]['extension'] ?? $tryExtension;
                $evidenceFilename =
                    is_numeric($sourceDatum['location'])
                        ? sprintf('%04d.%s', $sourceDatum['location'], $ext)
                        : sprintf('%s.%s', $sourceDatum['location'], $ext);

                $tryEvidencePath = 'evidences/' . $sourceId . '/' . $evidenceFilename;
                if (is_file(__DIR__ . '/../' . $tryEvidencePath)) {
                    $evidencePath = $tryEvidencePath;
                    break;
                }
            }

            if (!$evidencePath) {
                die($tryEvidencePath . ' not found.');
            }

            $sources[] = array_merge($sourceDatum, [
                'id' => $sourceId,
                'idx' => $idx,
                'imageData' => base64_encode(file_get_contents($imagePath)),
                'evidencePath' => $evidencePath,
                'locationText' => $sourceDatum['locationText'] ?? (is_numeric($sourceDatum['location']) ? 'P.' . $sourceDatum['location'] : $sourceDatum['location']),
            ]);
        }
    }

    $charData['srcCount'] = count($sources);
    $charData['srcN'] = $sources;
    $charData['remarksHTML'] =
        isset($charData['remarks'])
            ? str_replace('<a ', '<a target="blank"', \Parsedown::instance()->parse($charData['remarks']))
            : '';

    $characters[$tag][$charId] = $charData;
}

uksort($characters, function ($a, $b) {
    if ($a === 'UNTAGGED') {
        return -1;
    }

    if ($b === 'UNTAGGED') {
        return 1;
    }

    return strcmp($a, $b);
});

$result = $twig->render('index.html.twig', [
    'characters' => $characters,
    'sources' => $data['sources'],
]);

file_put_contents(__DIR__ . '/../index.html', $result);
