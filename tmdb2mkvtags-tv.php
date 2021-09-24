#!/usr/bin/env php
<?php
/**
 * Generate a Matroska tags file from TMDb information
 *
 * PHP version 7
 *
 * @author  Christian Weiske <cweiske@cweiske.de>
 * @author  Joe Rogers
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 * @link    https://www.themoviedb.org/
 * @link    https://www.matroska.org/technical/tagging.html
 * @link    https://developers.themoviedb.org/3/
 */

require_once("MkvTagXMLWriter.php");
require_once("commonTmdb.php");

$apiToken = null;

$language = null;
$title    = null;
$season   = null;
$episode  = null;
$outdir   = null;
$argIndex = 0;
for ($i = 1; $i < count($argv); $i++) {
    if (preg_match("/\-\-output=(.+)/", $argv[$i], $matches)) {
        $output = $matches[1];
    } else {
        $argIndex++;

        switch ($argIndex) {
            case 1:
                $language = $argv[$i];
                break;
            case 2:
                $title = $argv[$i];
                break;
            case 3:
                $season = $argv[$i];
                if (!is_numeric($season)) {
                    $season = null;
                }
                break;
            case 4:
                $episode = $argv[$i];
                if (!is_numeric($episode)) {
                    $episode = null;
                }
                break;
        } 
    }
}

if ($title === null) {
    fwrite(STDERR, "Usage: tmdb2mkvtags-tv.php [--output=OUTDIR] LANGUAGE \"SHOW TITLE\"\n");
    exit(1);
}


$configFiles = [];
$configFiles[] = preg_replace('#-tv.php$#', '', $argv[0]) . '.config.php';
if (isset($_SERVER['XDG_CONFIG_HOME'])) {
    $configFiles[] = $_SERVER['XDG_CONFIG_HOME'] . '/tmdb2mkvtags.config.php';
} else if (isset($_SERVER['HOME'])) {
    $configFiles[] = $_SERVER['HOME'] . '/.config/tmdb2mkvtags.config.php';
}
$configFiles[] = '/etc/tmdb2mkvtags.config.php';
foreach ($configFiles as $configFile) {
    if (file_exists($configFile)) {
        include_once $configFile;
        break;
    }
}
if ($apiToken === null) {
    fwrite(STDERR, "API token is not set\n");
    fwrite(
        STDERR,
        "Configuration files tried:\n " . implode("\n ", $configFiles) . "\n"
    );
    exit(2);
}
if ($includeAdult === null) {
    $includeAdult = 'true';
}


$shows = queryTmdb(
    '/3/search/tv'
    . '?query=' . urlencode($title)
    . "&language=" . urlencode($language)
    . '&include_adult=' . urlencode($includeAdult)
);

if ($shows->total_results == 0) {
    fwrite(STDERR, "No shows found\n");
    exit(20);

} else if ($shows->total_results == 1) {
    $show = $shows->results[0];

} else {
    $page = 1;
    $itemsPerPage = 0;
    do {
        fwrite(STDERR, sprintf("Found %d shows\n", $shows->total_results));
        foreach ($shows->results as $key => $show) {
            fwrite(
                STDERR,
                sprintf(
                    "[%2d] %s (%s)\n",
                    $key + ($page -1) * $itemsPerPage,
                    $show->name,
                    property_exists($show, "first_air_date") ? $show->first_air_date : ""
                )
            );
        }
        if ($page > 1) {
            fwrite(STDERR, "p: previous page\n");
        }
        if ($shows->total_pages > $page) {
            $itemsPerPage = count($shows->results);
            fwrite(STDERR, "n: next page\n");
        }
        fwrite(STDERR, "\n");
        fwrite(STDERR, 'Your selection: ');
        $cmd = readline();
        if (is_numeric($cmd)) {
            $num = $cmd - ($page - 1) * $itemsPerPage;
            if (isset($shows->results[$num])) {
                $show = $shows->results[$num];
                break;
            }
            fwrite(STDERR, "Invalid selection $num\n");
        } else if ($cmd == 'n' && $shows->total_pages > $page) {
            $page++;
        } else if ($cmd == 'p' && $page > 1) {
            $page--;
        } else if ($cmd == 'q' || $cmd == 'quit' || $cmd == 'exit') {
            exit(30);
        }

        $shows = queryTmdb(
            '/3/search/tv'
            . '?query=' . urlencode($title)
            . '&language=' . urlencode($language)
            . '&include_adult=' . urlencode($includeAdult)
            . '&page=' . $page
        );
    } while (true);
}

$showDetails = queryTmdb('3/tv/' . $show->id . '?language=' . $language);
$showIds = queryTmdb('3/tv/' . $show->id . '/external_ids');

$seasonMap = array();
foreach ($showDetails->seasons as $seasonDetails) {
    $seasonMap[$seasonDetails->season_number] = $seasonDetails;
}

while ($season === null || !isset($seasonMap[$season])) {
    if ($season !== null) {
        fwrite(STDERR, "Invalid season selection $season\n");
    }
    fwrite(STDERR, "Select a season:\n");
    foreach ($seasonMap as $number => $details) {
        fwrite(
            STDERR,
            sprintf(
                "[%2d] %s (%s) Episode Count: %d\n",
                $number,
                $details->name,
                $details->air_date,
                $details->episode_count
            )
        );
    }
    fwrite(STDERR, "\n");
    fwrite(STDERR, 'Your selection: ');
    $cmd = readline();
    if ($cmd == 'q' || $cmd == 'quit' || $cmd == 'exit') {
        exit(30);
    } else {
        $season = $cmd;
    }
}

$seasonDetails = queryTmdb('3/tv/' . $show->id . '/season/' . $season);

$episodeMap = array();
foreach ($seasonDetails->episodes as $episodeDetails) {
    $episodeMap[$episodeDetails->episode_number] = $episodeDetails;
}

while ($episode === null || !isset($episodeMap[$episode])) {
    if ($episode !== null) {
        fwrite(STDERR, "Invalid episode selection $episode\n");
    }
    fwrite(STDERR, "Select a episode:\n");
    foreach ($episodeMap as $number => $details) {
        fwrite(
            STDERR,
            sprintf(
                "[%2d] %s (%s)\n",
                $number,
                $details->name,
                $details->air_date
            )
        );
    }
    fwrite(STDERR, "\n");
    fwrite(STDERR, 'Your selection: ');
    $cmd = readline();
    if ($cmd == 'q' || $cmd == 'quit' || $cmd == 'exit') {
        exit(30);
    } else {
        $episode = $cmd;
    }
}

// TODO allow multi-selection
// TODO choice for multi: bundle or individual

$episodeDetails = $episodeMap[$episode];
$credits = queryTmdb('3/tv/' . $show->id . '/season/' . $season . '/episode/' . $episode . '/credits?language=' . $language);

$downloadImages = true;

$xml = new MkvTagXMLWriter();
if ($outdir === '-') {
    $xml->openMemory();
    $downloadImages = false;
    fwrite(STDERR, "Not downloading images\n");
} else {
    if ($outdir === null) {
        $outdir = trim(str_replace('/', ' ', $show->name))
            . '/' . trim(str_replace('/', ' ', $seasonDetails->name))
            . '/' . trim(str_replace('/', ' ', $episodeDetails->name));
    }
    $outdir = rtrim($outdir, '/') . '/';
    if (is_file($outdir)) {
        fwrite(STDERR, "Error: Output directory is a file\n");
        exit(2);
    }
    fwrite(STDERR, $outdir);
    if (!is_dir($outdir)) {
        mkdir($outdir, recursive: true);
    }
    $outfile = $outdir . 'mkvtags.xml';
    $xml->openURI($outfile);
}

$xml->setIndent(true);
$xml->startDocument("1.0");
$xml->writeRaw("<!DOCTYPE Tags SYSTEM \"matroskatags.dtd\">\n");

$xml->startElement("Tags");


$xml->startComment();
$xml->text('Show information');
$xml->endComment();

$xml->startElement("Tag");
$xml->targetType(70);
$xml->simple('TITLE', $showDetails->name, $language);
// TODO TOTAL_PARTS for season count if show ended?
$xml->simple('TMDB', 'tv/' . $show->id);
$xml->simple('IMDB', $showIds->imdb_id);
$xml->endElement();

$xml->startComment();
$xml->text('Season information');
$xml->endComment();

$xml->startElement("Tag");
$xml->targetType(60);
$xml->simple('PART_NUMBER', $seasonDetails->season_number);
$xml->simple('DATE_RELEASED', $seasonDetails->air_date);
$xml->simple('TOTAL_PARTS', count($episodeMap));
$xml->endElement();



$xml->startComment();
$xml->text('Episode information');
$xml->endComment();

$xml->startElement("Tag");

$xml->targetType(50);
$xml->simple('TITLE', $episodeDetails->name, $language);
$xml->simple('PART_NUMBER', $episodeDetails->episode_number);
$xml->simple('SYNOPSIS', $episodeDetails->overview, $language);

$xml->simple('DATE_RELEASED', $episodeDetails->air_date);

$xml->simple('RATING', $episodeDetails->vote_average / 2);//0-10 on TMDB, 0-5 mkv

if ($language != $show->original_language) {
    $xml->startElement('Simple');
    $xml->startElement('Name');
    $xml->text('ORIGINAL');
    $xml->endElement();
    $xml->simple('TITLE', $show->original_title, $show->original_language);
    $xml->endElement();//Simple
}


foreach ($credits->cast as $actor) {
    $xml->actor($actor->name, $actor->character);
}
foreach ($credits->guest_stars as $actor) {
    $xml->actor($actor->name, $actor->character);
}
foreach ($credits->crew as $crewmate) {
    if (isset($crewMap[$crewmate->job])) {
        $xml->simple($crewMap[$crewmate->job], $crewmate->name);
    } else {
        fwrite(STDERR, "Unknown crew mapping: " . $crewmate->job . "\n");
    }
}


$xml->endElement();//Tag
$xml->endElement();//Tags
$xml->endDocument();

if ($outdir === null) {
    echo $xml->outputMemory();
} else {
    $xml->flush();
}


if ($downloadImages) {
    //we take the largest scaled image, not the original image
    $tmdbConfig = queryTmdb('3/configuration');
    foreach ($tmdbConfig->images as $key => $sizes) {
        if (is_array($sizes)) {
            foreach ($sizes as $sizeKey => $value) {
                if ($value == 'original') {
                    unset($tmdbConfig->images->$key[$sizeKey]);
                }
            }
        }
    }

    if ($episodeDetails->still_path) {
        $size = $tmdbConfig->images->poster_sizes[
            array_key_last($tmdbConfig->images->poster_sizes)
        ];
        $url = $tmdbConfig->images->secure_base_url . $size . $episodeDetails->still_path;
        $imagePath = $outdir
            . 'cover.' . pathinfo($episodeDetails->still_path, PATHINFO_EXTENSION);
        if (!file_exists($imagePath)) {
            file_put_contents($imagePath, file_get_contents($url));
        }
    }
}


if ($outdir !== '-') {
    $fulldir = realpath($outdir);
    fwrite(STDERR, "Files written into directory:\n$fulldir\n");
}



//var_dump($credits);
//var_dump($show, $details);

?>
