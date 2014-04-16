<?php

include ( "config.php" );
include ( "functions/global.php" );

is_file( $argv[1] ) ? exec( 'resources/pgn-extract '.$argv[1].' --fencomments', $PGN ) : die( "not a file\n" );

if ( $PGN === FALSE ) die ( "file has no contents\n" );

preg_match_all( "/\{ (.*?) \}/", implode( ' ', $PGN ), $positions);

$positions = $positions[1];

volatilityAnalysis( $positions );
