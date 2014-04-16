<?php

function getUci ( $position, $moveTime, $multiPv = 1 ) {

	global $STOCKFISH_PATH, $STOCKFISH_THREADS;

	$descriptorspec = array(
		0 => array( "pipe", "r" ),  // stdin is a pipe that the child will read from
		1 => array( "pipe", "w" ),  // stdout is a pipe that the child will write to
		2 => array( "file", "/tmp/error-output.txt", "a" ) // stderr is a file to write to
	);

	$cwd = '/tmp';

	$process = proc_open( "$STOCKFISH_PATH", $descriptorspec, $pipes, $cwd );

	if (is_resource($process)) {

		fwrite( $pipes[0], "uci\n" );
		fwrite( $pipes[0], "ucinewgame\n" );
		fwrite( $pipes[0], "isready\n" );
		fwrite( $pipes[0], "setoption name MultiPV value $multiPv\n" );
		if ( is_int( $STOCKFISH_THREADS ) && isset( $STOCKFISH_THREADS ) ) {
			fwrite( $pipes[0], "setoption name Threads value $STOCKFISH_THREADS\n" );
		}
		fwrite( $pipes[0], "position fen $position\n" );
		fwrite( $pipes[0], "go movetime $moveTime\n" );
		usleep( 1000 * $moveTime + 100 );
		fwrite( $pipes[0], "quit\n" );
		fclose( $pipes[0] );

		$output = stream_get_contents( $pipes[1] );

		fclose( $pipes[1] );
	}
	//print_r($output);
	return $output;
}

function parseUciToArray ( $uciString ) {
	preg_match_all( "/cp (-?\d*).*multipv (\d*) pv ([a-h1-8]{4})/", $uciString, $matches, PREG_SET_ORDER );

	return $matches;
}

function volatilityAnalysis ( $positions ) {
	global $EVAL_TIME, $STOCKFISH_MULTIPV, $GOOD_MOVE;

	if ( !is_int( $STOCKFISH_MULTIPV ) || $STOCKFISH_MULTIPV < 1 ) {
		$STOCKFISH_MULTIPV = 1;
	}

	if ( !is_int( $EVAL_TIME ) || $EVAL_TIME < 1 ) {
		$EVAL_TIME = 500;
	}

	$side = 1; // 1 = black, 0 = black

	foreach ( $positions as $moveNum => $position ) {

		$uciArray = parseUciToArray( getUci( $position, $EVAL_TIME, $STOCKFISH_MULTIPV ) );

		$uniquePV = array();

		array_reverse( $uciArray );

		$pv = $STOCKFISH_MULTIPV;

		foreach ( $uciArray as $array ) {
		 	if ( $pv > 0 ) {
		 		if ( !isset( $uniquePV[ $array[3] ] ) ) {
		 			$uniquePV[ $array[3] ] = $array[1];
		 		}
				$pv--;
		 	} else {
		 		break;
		 	}
		}

		$topMove = '';
		$goodMoves = 0;

		foreach ( $uniquePV as $move => $eval ) {
			if ( $topMove === '' ) {
				$topMove = $eval - abs( $eval * $GOOD_MOVE );
			} else if ( $eval > $topMove ) {
				$goodMoves++;
			}
		}

		$percentageVol = 1 - $goodMoves / count( $uniquePV );

		if ( $side == 0 ) {
			echo str_pad ( str_pad( '', ceil ( 10 * $percentageVol ), '=' ) , 10 , ' ', STR_PAD_LEFT ) . 100 * $percentageVol . "\n";
			$side = 1;
		} else {
			echo "          " . str_pad ( 100 * $percentageVol , 3 ) . str_pad ( str_pad( '', ceil ( 10 * $percentageVol ), '=' ) , 10 , ' ' ) . "\n"; 
			$side = 0;
		}	
	}
}