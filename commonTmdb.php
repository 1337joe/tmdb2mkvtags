<?php
/**
 * Generate a Matroska tags file from TMDb information
 *
 * PHP version 7
 *
 * @author  Christian Weiske <cweiske@cweiske.de>
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 * @link    https://www.themoviedb.org/
 * @link    https://www.matroska.org/technical/tagging.html
 * @link    https://developers.themoviedb.org/3/
 */

function queryTmdb($path)
{
    global $apiToken;

    $url = 'https://api.themoviedb.org/' . $path;
    $ctx = stream_context_create(
        [
            'http' => [
                'timeout'       => 5,
                'ignore_errors' => true,
                'header'        => 'Authorization: Bearer ' . $apiToken
            ]
        ]
    );
    $res = file_get_contents($url, false, $ctx);
    list(, $statusCode) = explode(' ', $http_response_header[0]);
    $data = json_decode($res);

    if ($statusCode != 200) {
        if (isset($data->status_code) && isset($data->status_message)) {
            throw new Exception(
                'API error: ' . $data->status_code . ' ' . $data->status_message
            );
        }
        throw new Exception('Error querying API: ' . $statusCode, $statusCode);
    }
    return $data;
}

//map tmdb job to matroska tags
$crewMap = [
    'Art Direction'           => 'ART_DIRECTOR',
    'Costume Design'          => 'COSTUME_DESIGNER',
    'Director of Photography' => 'DIRECTOR_OF_PHOTOGRAPHY',
    'Director'                => 'DIRECTOR',
    'Assistant Director'      => 'ASSISTANT_DIRECTOR',
    'Editor'                  => 'EDITED_BY',
    'Novel'                   => 'WRITTEN_BY',
    'Original Music Composer' => 'COMPOSER',
    'Conductor'               => 'CONDUCTOR',
    'Producer'                => 'PRODUCER',
    'Screenplay'              => 'WRITTEN_BY',
    'Sound'                   => 'COMPOSER',
    'Theme Song Performance'  => 'LEAD_PERFORMER',
    'Writer'                  => 'WRITTEN_BY',
    'Choreographer'           => 'CHOREGRAPHER',
];

?>