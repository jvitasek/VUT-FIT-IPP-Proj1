# VUT-FIT-IPP-Proj1
The script carries out processing an input query similar to the SELECT command of SQL on an input file/stdin formatted in XML. The output is an XML file or XML outputted to stdout.

Usage:<br>
  -n			Do not generate the XML header on the output of the script.<br>
  --help		Print the help statement.<br>
  --input=<filename>	Set the input file in XML format.<br>
  --output=<filename>	Set the output file in XML format.<br>
  --query=<`query`>	Set a query in the language defined by the assignment.<br>
  --qf=<filename>	Set a query located in a file in the language defined by the assignment.<br>
  --root=<element>	The name of the pair root element encapsulating the results.

# Example:
<pre>php xqr.php --input=input.xml --query="SELECT book FROM library" --output=test.out --root=Wrapper</pre>
