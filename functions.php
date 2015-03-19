<?php

#XQR:xvitas02
/**
 * This module contains all the needed functions to carry out the query search on an XML input.
 *
 * https://github.com/jvitasek
 * @author Jakub Vitasek <xvitas02@stud.fit.vutbr.cz>
 * @copyright 2015
 */

/**
 * Prints help.
 * Prints all the info on how to use the program's options.
 * @return void
 */
function print_help() {
	echo "Usage:\n";
	echo "  -n\t\t\t" . "Do not generate the XML header on the output of the script.\n";
	echo "  --help\t\t" . "Print the help statement.\n";
	echo "  --input=<filename>\t" . "Set the input file in XML format.\n";
	echo "  --output=<filename>\t" . "Set the output file in XML format.\n";
	echo "  --query=<`query`>\t" . "Set a query in the language defined by the assignment.\n";
	echo "  --qf=<filename>\t" . "Set a query located in a file in the language defined by the assignment.\n";
	echo "  --root=<element>\t" . "The name of the pair root element encapsulating the results.\n";
	exit(0);
}

/**
 * Gets user options.
 * Does all the parsing of user arguments, both shortopts and longopts.
 * @return void
 */
function get_options() {
	// parsing the user side arguments
	$shortopts = "";
	$shortopts .= "n"; // do not generate xml header

	$longopts = array(
		"help",
		"input:", // input file
		"output:", // output file
		"query:", // query to run
		"qf:", // query to run
		"root:", // root element
	);

	$args = getopt($shortopts, $longopts);

	global $xml_header, $input_file, $input_file_handle, $output_file, $output_file_handle,
		   $query, $query_file, $root_element;

	foreach($args as $key => $value) {
		switch($key) {
			case "n": // no xml header
				if($value === FALSE)
					$xml_header = 1;
				break;
			case "help": // help statement
				if($value === FALSE)
					print_help();
				break;
			case "input": // input file
				$input_file = $value;
				break;
			case "output": // output file
				$output_file = $value;
				break;
			case "query": // query string
				$query = $value;
				break;
			case "qf": // query file
				$query_file = $value;
				break;
			case "root": // root element
				$root_element = $value;
				break;
		}
	}

	if($query && $query_file)
		call_error("QUERY_ERR", 1); // *1* wrong format of arguments
	if(!$query && !$query_file)
		call_error("NOQUERY_ERR", 1); // *1* wrong format of arguments
}

/**
 * Checks a file.
 * Checks if the file specified exists and validates readability/writability
 * based on the second parameter.
 * @param string $filename The name of the file to be checked.
 * @param string $mode r, w or rw -> the mode to check.
 * @return void
 */
function check_file($filename, $mode) {
	// check for readability/writability
	if($mode == "r")
	{
		// check for readability
		if(!is_readable($filename))
			call_error("UNREADABLE_ERR", 2);
		// check for existance
		if(!file_exists($filename))
			call_error("NONEXISTENT_ERR", 2);
	}
}

/**
 * Parses a query.
 * Parses a query from input to an associative array which is then returned.
 * @param string $query The query passed through user arguments.
 * @return array An associative array with the structure: keyword => value.
 */
function parse($query) {
	global $limit;
	/**
	 * Checking the basic constraints of the query.
	 * @see check_query() for more.
	 */
	$order = "/(SELECT.*(LIMIT)?.*FROM.*(WHERE)?.*(ORDER BY)?.*)/";
	if(!preg_match($order, $query))
		call_error("KEYWORD_ERR", 80);

	// parsing different keywords
	preg_match("#(?<=SELECT\s)\s*(\w+)#", $query, $res["SELECT"]); // select
	preg_match("#(?<=LIMIT\s)\s*(\d*\.?\d*)#", $query, $res["LIMIT"]); // limit (taking in floats to call errors, that's why the point)
	preg_match("#(?<=FROM\s)\s*(\w*\.*\w*)#", $query, $res["FROM"]); // from
	preg_match("#(?<=WHERE NOT\s)\s*(\w*\.*\w*)#", $query, $res["WHERE NOT"]); // negative where
	if(!$res["WHERE NOT"])
		preg_match("#(?<=WHERE\s)\s*(\w*\.*\w*)#", $query, $res["WHERE"]); // positive where
	if(isset($res["WHERE"][1]))
		preg_match("#(?<=". $res["WHERE"][1] ."\s)\s*(=|<|>|CONTAINS)#", $query, $res["RELATION"]); // positive <, >, =, contains
	elseif(isset($res["WHERE NOT"][1]))
		preg_match("#(?<=". $res["WHERE NOT"][1] ."\s)\s*(=|<|>|CONTAINS)#", $query, $res["RELATION"]); // negative <, >, =, contains
	if(isset($res["RELATION"][1]))
		preg_match("#(?<=". $res["RELATION"][1] ."\s)\s*(?:\"|')(.*)(?=\"|')#", $query, $res["WHERE ELEM"]); // string
	if(empty($res["WHERE ELEM"][1]))
		preg_match("#(?<=". $res["RELATION"][0] ."\s)\s*(\d*\.?\d*)#", $query, $res["WHERE ELEM"]); // number
	preg_match("#(?<=ORDER BY\s)\s*(\w+)#", $query, $res["ORDER BY"]); // order by
	if($res["ORDER BY"][1])
		preg_match("#(?<=". $res["ORDER BY"][1] ."\s)\s*(ASC|DESC)#", $query, $res["ASC/DESC"]); // asc || desc

	// SELECT: [0] => element
	if(isset($res["SELECT"][1]))
		$parsed["SELECT"] = $res["SELECT"][1];
	// LIMIT: [0] => limit number
	if($res["LIMIT"])
	{
		$parsed["LIMIT"] = $res["LIMIT"][1]; // a bit redundant, but prettier
		$limit = $parsed["LIMIT"]; // setting the global var to use when printing results
		// checking for a possible float value
		$float_limit = floatval($limit);
		if($float_limit && intval($float_limit) != $float_limit)
			call_error("LIMIT_ERR", 80);
	}
	// FROM: [0] => element, [1] => attribute
	if($res["FROM"][1])
		$parsed["FROM"] = preg_split("/[.]/", $res["FROM"][1]);
	// WHERE (NOT): [0] => element, [1] => attribute, [2] => relation, [3] => literal
	if($res["WHERE NOT"])
	{
		$parsed["WHERE NOT"] = preg_split("/[.]/", $res["WHERE NOT"][1]);
		$parsed["WHERE NOT"][2] = $res["RELATION"][1];
		$parsed["WHERE NOT"][3] = $res["WHERE ELEM"][1];
	}
	else
	{
		if($res["WHERE"])
		{
			$parsed["WHERE"] = preg_split("/[.]/", $res["WHERE"][1]);
			$parsed["WHERE"][2] = $res["RELATION"][1];
			$parsed["WHERE"][3] = $res["WHERE ELEM"][1];
		}
	}
	// ORDER BY: [0] => element, [1] => ASC || DESC
	if($res["ORDER BY"])
	{
		$parsed["ORDER BY"][0] = $res["ORDER BY"][1];
		$parsed["ORDER BY"][1] = $res["ASC/DESC"][1];
	}

	return $parsed; // return the associative array of keys and values
}

/**
 * Checks a given query.
 * Checks a query for possible errors and calls the error function if needed.
 * @param array $query The query formatted into a specifically designed array.
 * @return void
 */
function check_query($query) {
	// if there is no SELECT
	if(!isset($query["SELECT"]))
		call_error("KEYWORD_ERR", 80);
	// checking SELECT and FROM for equality
	if($query["FROM"][0] == $query["SELECT"])
		call_error("KEYWORD_ERR", 80);
	// checking FROM
	if(!($query["FROM"][0]) && !($query["FROM"][1]))
		call_error("KEYWORD_ERR", 80);
	// if WHERE is supplied, checking WHERE
	if(isset($query["WHERE"][0]) || isset($query["WHERE"][1]))
	{
		if(!($query["WHERE"][2]) || !($query["WHERE"][3]))
			call_error("KEYWORD_ERR", 80);
		// checking for a float
		$float_literal = floatval($query["WHERE"][3]);
		if($float_literal && intval($float_literal) != $float_literal)
			call_error("LITERAL_ERR", 80);
	}
	// if WHERE NOT is supplied, checking WHERE NOT
	if(isset($query["WHERE NOT"][0]) || isset($query["WHERE NOT"][1]))
	{
		if(!($query["WHERE NOT"][2]) || !($query["WHERE NOT"][3]))
			call_error("KEYWORD_ERR", 80);
	}

	// if ORDER BY is supplied, checking ORDER BY
	if(((isset($query["ORDER BY"][0])) && !($query["ORDER BY"][1])) || (!isset($query["ORDER BY"][0]) && (isset($query["ORDER BY"][1]))))
		call_error("KEYWORD_ERR", 80);
}

/**
 * Searches the XML.
 * Carries out the search constrained by the input query.
 * @param array $query The query formatted into a specifically designed array.
 * @param SimpleXMLElement $xml_obj The simpleXML object containing the input XML.
 * @return SimpleXMLElement The result simpleXML object.
 */
function searchXML($query, $xml_obj) {
	/**
	 * The command for xpath to be called.
	 * @var string
	 */
	$select_xpath = "";
	$from_xpath = "";
	$where_xpath = "";

	//=====================================
	// PROCESSING THE "FROM" CLAUSE
	//=====================================

	if($query["FROM"][0] == "ROOT") // getting rid of the ROOT literal 
		$query["FROM"][0] = "";

	// element and no attribute (el)
	if(($query["FROM"][0]) && !isset($query["FROM"][1]))
		$from_xpath .= "//" . $query["FROM"][0] . "[1]"; // [1] => first occurence
	// attribute and no element (.attr)
	elseif(!($query["FROM"][0]) && isset($query["FROM"][1]))
		$from_xpath .= "(//@" . $query["FROM"][1] . "/..)[1]"; // [1] => first occurence
	// both element and attribute (el.attr)
	elseif(($query["FROM"][0]) && ($query["FROM"][1]))
		$from_xpath .= "//" . $query["FROM"][0] . "[@" . $query["FROM"][1] . "][1]"; // [1] => first occurence
	else
		$from_xpath .= "";

	//=====================================
	// PROCESSING THE "SELECT" CLAUSE
	//=====================================

	if($query["SELECT"] == "*" || $query["SELECT"] == "")
		$select_xpath = $from_xpath;
	else
		$select_xpath = "//" . $query["SELECT"];

	//=====================================
	// PROCESSING THE "WHERE" CLAUSE
	//=====================================

	if(isset($query["WHERE"]))
	{
		// element and no attribute (el)
		if(($query["WHERE"][0]) && !isset($query["WHERE"][1]))
		{
			// contains
			if($query["WHERE"][2] == "CONTAINS")
			{
				if($query["WHERE"][0] == $query["SELECT"]) // to est
					$where_xpath = "[contains(text(), '" . $query["WHERE"][3] . "')]";
				else
					$where_xpath = "[contains(.//" . $query["WHERE"][0] . ", '" . $query["WHERE"][3] . "')]";
			}
			// relational operator
			else
				$where_xpath = "[" . $query["WHERE"][0] . $query["WHERE"][2] . "'" . $query["WHERE"][3] . "']";
		}
		// attribute and no element (.attr)
		elseif(!($query["WHERE"][0]) && ($query["WHERE"][1]))
		{
			// contains
			if($query["WHERE"][2] == "CONTAINS")
				$where_xpath = "[contains(@" . $query["WHERE"][1] . ", '" . $query["WHERE"][3] . "')]";
			// relational operator
			else
				$where_xpath = "[@" . $query["WHERE"][1] . $query["WHERE"][2] . "'" . $query["WHERE"][3] . "']";
		}
		// both element and attribute (el.attr)
		elseif(($query["WHERE"][0]) && ($query["WHERE"][1]))
		{
			// contains
			if($query["WHERE"][2] == "CONTAINS")
			{
				if($query["WHERE"][0] == $query["SELECT"])
					$where_xpath = "[.//" . $query["WHERE"][0] . "[contains(text(), '" . $query["WHERE"][3] . "')]]";
				else
					$where_xpath = "[.//" . $query["WHERE"][0] . "[contains(@" . $query["WHERE"][1] . ", '" . $query["WHERE"][3] . "')]]";
			}
			// relational operator
			else
				$where_xpath = "[.//" . $query["WHERE"][0] . "[@" . $query["WHERE"][1] . $query["WHERE"][2] . "'" . $query["WHERE"][3] . "']]";
		}
		else
			$where_xpath = "";
	}

	//=====================================
	// PROCESSING THE "WHERE NOT" CLAUSE
	//=====================================

	if(isset($query["WHERE NOT"]))
	{
		// element and no attribute (el)
		if(($query["WHERE NOT"][0]) && !($query["WHERE NOT"][1]))
		{
			// contains
			if($query["WHERE NOT"][2] == "CONTAINS")
			{
				if($query["WHERE NOT"][0] == $query["SELECT"]) // to est
					$where_xpath = "[not(contains(text(), '" . $query["WHERE NOT"][3] . "'))]";
				else
					$where_xpath = "[not(contains(.//" . $query["WHERE NOT"][0] . ", '" . $query["WHERE NOT"][3] . "'))]";
			}
			// relational operator
			else
				$where_xpath = "[not(" . $query["WHERE NOT"][0] . $query["WHERE NOT"][2] . "'" . $query["WHERE NOT"][3] . "')]";
		}
		// attribute and no element (.attr)
		elseif(!($query["WHERE NOT"][0]) && ($query["WHERE NOT"][1]))
		{
			// contains
			if($query["WHERE NOT"][2] == "CONTAINS")
				$where_xpath = "[not(contains(@" . $query["WHERE NOT"][1] . ", '" . $query["WHERE NOT"][3] . "'))]";
			// relational operator
			else
				$where_xpath = "[not(@" . $query["WHERE NOT"][1] . $query["WHERE NOT"][2] . "'" . $query["WHERE NOT"][3] . "')]";
		}
		// both element and attribute (el.attr)
		elseif(($query["WHERE NOT"][0]) && ($query["WHERE NOT"][1]))
		{
			// contains
			if($query["WHERE NOT"][2] == "CONTAINS")
			{
				if($query["WHERE NOT"][0] == $query["SELECT"])
					$where_xpath = "[not(.//" . $query["WHERE NOT"][0] . "[contains(text(), '" . $query["WHERE NOT"][3] . "')])]";
				else
					$where_xpath = "[not(.//" . $query["WHERE NOT"][0] . "[contains(@" . $query["WHERE NOT"][1] . ", '" . $query["WHERE NOT"][3] . "')])]";
			}
			// relational operator
			else
				$where_xpath = "[not(.//" . $query["WHERE NOT"][0] . "[@" . $query["WHERE NOT"][1] . $query["WHERE NOT"][2] . "'" . $query["WHERE NOT"][3] . "'])]";
		}
		else
			$where_xpath = "";
	}

	//=====================================
	// PUTTING THE RESULTING PATH TOGETHER
	//=====================================

	$final_xpath = $from_xpath . $select_xpath . $where_xpath;
	$result_obj = $xml_obj->xpath($final_xpath);

	if(!$result_obj) // if nothing
		return NULL;

	if(isset($query["ORDER BY"]))
		$result_obj = orderXML($xml_obj, $result_obj, $query);

	return $result_obj; // return the SimpleXML object
}

/**
 * Orders the output.
 * Orders the resulting XML by the given query parameters.
 * @param SimpleXMLElement $xml_obj The initial simpleXML object containing the input XML.
 * @param SimpleXMLElement $elements The resulting simpleXML object.
 * @param array $query The query formatted into a specifically designed array.
 * @return SimpleXMLElement
 */
function orderXML($xml_obj, $elements, $query) {
	// by which key we want to sort
	$sort_keys = $xml_obj->xpath("//" . $query["ORDER BY"][0]);

	// SORT_ASC
	if($query["ORDER BY"][1] == "ASC")
		array_multisort($sort_keys, SORT_NUMERIC, SORT_ASC, $elements);
	// SORT_DESC
	else
		array_multisort($sort_keys, SORT_NUMERIC, SORT_DESC, $elements);

	return $elements; // return the sorted simpleXML object
}

/**
 * Prints the error message.
 * Outputs a message stating the reason of the error based on the error type passed.
 * @param string $type The type of the error (@see $errors[]).
 * @param int $retval The return value.
 * @return int The dedicated return value as set in the assignment.
 */
function call_error($type, $retval) {
	global $STDERR, $errors;
	fwrite($STDERR, $errors[$type]); // print the message to STDERR
	exit($retval); // exit with the passed-in value
}

/**
 * Format the resulting xml.
 * Format the results into an xml string based on the arguments given.
 * @param SimpleXMLElement $xml The simpleXML object containing the query search results.
 * @return string The final string to be used as a result.
 */
function format_results($xml, $query) {
	global $root_element, $xml_header, $limit;
	$final_xml = ""; // the resulting string
	$i = -1; // helper counter
	$len = sizeof($xml); // the number of passes

	// if there is nothing in the array
	if(empty($xml))
	{
		if($root_element)
			$final_xml = "<" . $root_element . "/>";
		return $final_xml;
	}

	foreach($xml as $result) {
		$i++;
		if($limit == 0) // if LIMIT 0
		{
			if($root_element)
				$final_xml = "<" . $root_element . "/>";
			break; // end
		}
		if($i == 0 && $xml_header == 0) // if we're at the start and no -n
			$final_xml .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>"; // xml header
		if($i == 0 && $root_element) // if we're at the start and --root is defined
			$final_xml .= "<" . $root_element . ">"; // beginning
		if(($limit) && ($i == $limit)) // if we've reached the limit
		{
			if($root_element)
				$final_xml .= "</" . $root_element . ">"; // end
			break; // end
		}
		// adding order attribute
		if(isset($query["ORDER BY"]))
			$result->addAttribute('order', $i+1);
		$final_xml .= $result->asXML(); // append the XML format of the searchXML results
		if($i == $len-1 && $root_element) // if we've reached the end and --root is defined
			$final_xml .= "</" . $root_element . ">"; // end
	}
	$final_xml .= "\n"; // I hope this line won't get me 0 points
	return $final_xml; // return the final result
}
