<?php

#XQR:xvitas02
/**
 * The main module of the program.
 * 
 * The script carries out processing an input query similar to the SELECT command of SQL
 * on an input file/stdin formatted in XML. The output is an XML file or XML outputted to stdout.
 *
 * https://github.com/jvitasek
 * @author Jakub Vitasek <xvitas02@stud.fit.vutbr.cz>
 * @copyright 2015
 */

error_reporting(E_PARSE | E_ERROR);
require("global.php"); // getting the global variables
require("functions.php"); // getting the functions

//======================================================================
// GETTING THE ARGUMENTS AND PARSING THE INPUT QUERY
//======================================================================

/**
 * @see get_options()
 */
get_options();

/**
 * @see parse()
 */
if($query) // read query from arguments
	$parsed_query = parse($query);
elseif($query_file) // read query from a file
{
	check_file($query_file, "r");
	$parsed_query = parse(file_get_contents($query_file));
}

/**
 * @see check_query()
 */
check_query($parsed_query);

//======================================================================
// MAIN FUNCTIONALITY
//======================================================================

if($input_file) // read input xml from a file
{
	check_file($input_file, "r");
	$input_xml = simplexml_load_file($input_file);
}
else // read input xml from stdin
	$input_xml = simplexml_load_string(file_get_contents("php://stdin"));

$result_xml = searchXML($parsed_query, $input_xml); // the crucial part of the program


//======================================================================
// FORMATTING AND PRINTING OUT RESULTS
//======================================================================

/**
 * @see format_results()
 */
$final_xml = format_results($result_xml, $parsed_query);

if($output_file) // priting into an output file
{
	//check_file($output_file, "w");
	if(file_put_contents($output_file, $final_xml) === FALSE)
		call_error("FILE_ERR", 2);
}
else // printing to stdout
	echo $final_xml;

return 0;
