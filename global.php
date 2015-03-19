<?php

#XQR:xvitas02
/**
 * This module contains all the global variables.
 *
 * https://github.com/jvitasek
 * @author Jakub Vitasek <xvitas02@stud.fit.vutbr.cz>
 * @copyright 2015
 */

// global variables
$STDERR = fopen('php://stderr', 'w+');
$STDIN = fopen('php://stdin', 'r');
$xml_header = 0;
$limit = -1;
$input = "";
$input_file = "";
$input_file_handle;
$output_file = "";
$output_file_handle;
$query = "";
$query_file = "";
$root_element = "";

// multiple errors to echo while handling errors
$errors = array(
	"QUERY_ERR" => "Can't have both query and query file!\n",
	"NOQUERY_ERR" => "No query entered!\n",
	"KEYWORD_ERR" => "The query is in a wrong format!\n",
	"NONEXISTENT_ERR" => "The file specified is non-existent!\n",
	"UNREADABLE_ERR" => "The file specified is not readable!\n",
	"UNWRITABLE_ERR" => "The file specified is not writable!\n",
	"FILE_ERR" => "There was an error while opening the query file!\n",
	"CLOSE_ERR" => "There was an error while closing the query file!\n",
	"OUTPUT_ERR" => "There was an error with the output file!\n",
	"INPUT_ERR" => "There was an error while opening the input file!\n",
	"LIMIT_ERR" => "Can't have a float limit value!\n",
	"LITERAL_ERR" => "The literal can't be a float!\n",
);
