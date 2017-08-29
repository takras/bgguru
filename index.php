<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set("allow_url_fopen", true);

// Replace with your own token from Slack configuration
$token = 'REPLACE WITH TOKEN';

if( $_GET['token'] != $token ) return false;

$response_url = $_GET['response_url'];
$command = $_GET['command'];
$user_name = $_GET['user_name'];
$channel_name = $_GET['channel_name'];
$channel_Id = $_GET['channel_id'];
$token = $_GET['token'];
$search = $_GET['text'];

$curl = curl_init();

if( count( $search ) > 0 ) {

	$numeric = true;
	if( !is_numeric( $search ) ) {
		$search = str_replace('"', "", $search);
		$search = str_replace("'", "", $search);
		$numeric = false;
		$bgg = "http://www.boardgamegeek.com/xmlapi2/search?type=boardgame&query=" . urlencode( $search );
		$stream = fopen( $bgg, 'r' );
		$gameinfo = stream_get_contents( $stream );
		$xml = simplexml_load_string( $gameinfo );
		if( $xml['total'] == 1 ) {
			$search = (int)$xml->item['id'];
			$numeric = true;
			fclose($bgg);
		}
	}
	
	if( is_numeric( $search ) && $numeric ) {
		$bgg = "https://www.boardgamegeek.com/xmlapi2/thing?id=" . $search . "&stats=1";
	}

    $stream = fopen( $bgg, 'r' );
    $gameinfo = stream_get_contents( $stream );
    $xml = simplexml_load_string( $gameinfo );

    $result = array();
    // Go through all games
    foreach( $xml as $game ) {

    	$designers = [];
    	$artists = [];
    	foreach( $game->link as $linktype ) {
    		if( (string)$linktype['type'] == "boardgamedesigner" ) {
    			$designers[] = $linktype['value'];
    		} else if ( (string)$linktype['type'] == "boardgameartist" ) {
    			$artists[] = $linktype['value'];
    		}
    	}
    	$designer = implode(', ', $designers);
    	$artist = implode(', ', $artists);
        $players = 0;

    	$playingtime = (int)$game->minplaytime['value'] == (int)$game->maxplaytime['value'] ? (int)$game->playingtime['value'] : (int)$game->minplaytime['value'] . "-" . (int)$game->maxplaytime['value'];

    		$geekrating = round( (float)$game->statistics->ratings->bayesaverage['value'], 1 );
    		if( $geekrating == 0 ) {
    			$rating = round( (float)$game->statistics->ratings->average['value'], 1 );
    		} else {
    			$rating = $geekrating;
    		}
			
			$rating = round( (float)$game->statistics->ratings->average['value'], 1 );

            $result[] = array(
                'id' => (int)$game['id'],
                'bggid' => (int)$game['id'],
                'name' => (string)$game->name['value'],
                'thumb_url' => (string)$game->thumbnail,
                'image_url' => (string)$game->image,
                'players' => (int)$game->minplayers['value'] . '-' . (int)$game->maxplayers['value'],
                'description' => (string)$game->description,
                'link' => "http://www.boardgamegeek.com/boardgame/" . (int)$game['id'] . "/",
                'yearpublished' => ":new-calendar: " . (string)$game->yearpublished['value'],
                'playingtime' => ":alarm-clock: " . $playingtime,
                'designer' => ":writer: " . $designer,
                'artist' => ":palette: " . $artist,
                'rating' => $rating,
                'age' => (string)$game->minage['value'] . "+"
            );
    }

    if( $channel_name == "directmessage" || count( $result ) == 0 || count( $result ) > 10 ) {
		$target = '"username": "'. $_GET['user_name'] .'"';
	} else {
		$target = '"channel": "'. $channel_name.'"';
	}

    // Exactly 1 match
    if( count( $result ) == 1 ) {
    	$result = $result[0];
    	$response = '
		{
		    "text": "Fant dette spillet for <@' . $user_name .'>:",
		    '. $target .',
		    "username": "bgguru",
		    "response_type": "in_channel",
		    "attachments": [
		        {
		        	"color": "good",
		            "title": "'. $result["name"].'",
		            "title_link": "'. $result["link"] .'",
		            "text": "' .
		            substr($result["description"], 0, 250) .'...",
		            
		            "image_url": "'. $result["thumb_url"] .'",
		            "mrkdwn_in": ["text"],
                    "footer": "BoardgameGuru av Takras",
                    "fields": [
					{
                    	"title": ":family: Players ",
                    	"value": "'. $result["players"] .'",
                    	"short": true
                    },
                    {
                    	"title": ":alarm-clock: Spilletid ",
                    	"value": "'. $playingtime .'",
                    	"short": true
                    },
                    {
                    	"title": ":writer: Forfatter ",
                    	"value": "'. implode( '\n', $designers ) .'",
                    	"short": true
                    },
                    {
                    	"title": ":award: Rating ",
                    	"value": "'. $rating .'",
                    	"short": true
                    },
                    {
                    	"title": ":new-calendar: Publisert ",
                    	"value": "'. (string)$game->yearpublished['value'] .'",
                    	"short": true
                    },
                    {
                    	"title": ":age: Alder ",
                    	"value": "'. $result['age'] .'",
                    	"short": true
                    }
                    
                    ]
		        }
		    ]
		}';
		
    } elseif( count( $result ) == 0 ) {
    	$response = '{ "text" : "Fant ingen spill som passet søket.", '. $target.' }';

    } elseif( count( $result ) > 1 ) {
    	
    	$errortext = "Fant flere treff, viser opptil 10 første.";
    	$gameresult = '';
    	$counter = 0;
    	
    	foreach( $result as $game ) {
    		if( ++$counter > 10 ) break;
    		$gameresult = $gameresult .
    		'Skriv */bgg ' . $game["id"] . '* for _' . $game["name"] . '_\n';
    	}

    	$text = '{"text": "'. $errortext .'\n' .
		    $gameresult . '"
		}';
		
		$response = $text;
    } else {
    	$response = '{"text" : Noe uventet oppstod. Prøvd på nytt med et annet søk.", '. $target .', "color": "red" }';
    }
    
}


curl_setopt($curl, CURLOPT_URL, $response_url);
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_POSTFIELDS, $response);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$server_output = curl_exec ($curl);
curl_close ($curl);
fclose( $stream );

?>
