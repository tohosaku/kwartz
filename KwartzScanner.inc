<?php

###
### KwartzScanner.inc
###
### $Id: KwartzScanner.inc,v 0.1 2004/08/16 14:02:49 kwatch Exp $
###

require_once('KwartzException.inc');
require_once('KwartzUtility.inc');

//namespace Kwartz {

	class KwartzScannerException extends KwartzException {
		var $linenum;
		var $scanner;

		function __construct($msg, $scanner, $linenum=NULL) {
			if (! $linenum) {
				$linenum = $scanner->linenum();
			}
			parent::__construct("ScanError (line {$linenum}): " . $msg);
			$this->scanner = $scanner;
			$this->linenum = $linenum;
		}
	}

	define('EOF', -1);


	###
	### ex.
	###   $input = "if ($x > 0) { echo $x; }";
	###   $scanner = new Scanner($input);
	###   while (($token = $scanner->scan()) != NULL) {
	###	  $token_str = $scanner->token_str();
	###	  echo "token='$token', token_str='$token_str'\n";
	###   }
	###
	class KwartzScanner {
		private $input;			// string
		private $index;
		private $char;
		private $length;
		private $linenum = 1;
		private $token;
		private $token_str;
		private $next_token;
		private $next_token_str;

		private $reserved = array(
			'if'	   => 'if',
			'elseif'   => 'elseif',
			'elsif'	   => 'elsif',
			'else'	   => 'else',
			'foreach'  => 'foreach',
			'for'	   => 'for',
			'while'	   => 'while',
			'echo'	   => 'print',
			'print'	   => 'print',
			'macro'	   => 'macro',
			'expand'   => 'expand',
			'elem'	   => 'elem',
			'true'	   => 'true',
			'false'	   => 'false',
			'null'	   => 'null',
			'nil'	   => 'null',
			'set'	   => 'set',
			'end'	   => 'end',
		);

		//const EOF = -1;


		function __construct($input) {
			$this->input  = $input;
			$this->length = strlen($input);
			$this->index  = -1;
			$this->read();
		}

		function token() {
			return $this->token;
		}

		function token_str() {
			return $this->token_str;
		}

		function linenum() {
			return $this->linenum;
		}

		function read() {
			$this->index++;
			if ($this->index >= $this->length) {
				return $this->char = EOF;
			}
			$this->char = $this->input{$this->index};
			if ($this->char == "\n") {
				$this->linenum++;
			}
			return $this->char;
		}


		function scan() {
			if ($this->next_token) {
				$this->token	 = $this->next_token;
				$this->token_str = $this->next_token_str;
				$this->next_token     = NULL;
				$this->next_token_str = NULL;
				return $this->token;
			}

			$c = $this->char;
			//if ($c == EOF) {
			//	return $this->token = NULL;
			//}

			## ignore spaces and comments
			while (1) {
				## ignore spaces
				while (ctype_space($c)) {
					$c = $this->read();
				}

				## ignore comments
				if ($c == '#') {
					while (($c = $this->read()) != EOF && $c != "\n") {
						# nothing
					}
					if ($c == EOF) { break; }
					continue;
				}
				break;
			}

			##
			if ($c == EOF) {
				return $this->token = NULL;
			}

			## reserved-word or name
			if (ctype_alpha($c) || $c == '_') {
				$s = $c;
				while ( ($c = $this->read()) != EOF && (ctype_alnum($c) || $c == '_') ) {
					$s .= $c;
				}
				if (array_key_exists($s, $this->reserved)) {
					$this->token = $this->reserved[$s];
					//$this->token_str = $s;
				} else {
					$this->token = 'name';
					$this->token_str = $s;
				}
				return $this->token;
			}

			## integer or float
			if (ctype_digit($c)) {
				$s = $c;
				while (($c = $this->read()) != EOF && ctype_digit($c)) {
					$s .= $c;
				}
				if ($c == '.') {
					$s .= $c;
					while (ctype_digit($c = $this->read())) {
						$s .= $c;
					}
					$this->token = 'float';
				} else {
					$this->token = 'integer';
				}
				if (ctype_alpha($c)) {
					throw new KwartzScannerException("invalid number.", $this);
				}
				$this->token_str = $s;
				return $this->token;
			}

			## string
			if ($c == '"') {
				$s = '';
				$linenum = $this->linenum;
				while (($c = $this->read()) != EOF && $c != '"') {
					if ($c == '\\') {
						$c = $this->read();
						if ($c == EOF) { break; }
						switch ($c) {
						case 'n':  $c = "\n"; break;
						case 'r':  $c = "\r"; break;
						case 't':  $c = "\t"; break;
						}
					}
					$s .= $c;
				}
				if ($c == EOF) {
					throw new KwartzScannerException("string is not closed.", $this, $linenum);
				}
				assert($c == '"');
				$this->token_str = $s;
				$this->read();
				return $this->token = 'string';
			}

			if ($c == "'") {
				$this->token_str = '';
				$linenum = $this->linenum;
				while (($c = $this->read()) != EOF && $c != "'") {
					if ($c == '\\') {
						$c = $this->read();
						if ($c == EOF) { break; }
						if ($c != '\\' && $c != "'") {
							$this->token_str .= '\\';
						}
					}
					$this->token_str .= $c;
				}
				if ($c == EOF) {
					throw new KwartzScannerException("string is not closed.", $this, $linenum);
				}
				assert($c == "'");
				$this->read();
				return $this->token = 'string';
			}

			if ($c == '<' || $c == '>' || $c == '!') {
				$op = $c;
				$c = $this->read();
				if ($c == '=') {
					$c = $this->read();
					$op .= '=';
				}
				//$this->token_str = $op;
				return $this->token = $op;
			}

			if ($c == '=') {
				$op = $c;
				$c = $this->read();
				if ($c == '=' || $c == '>') {
					$c = $this->read();
					$op .= '=';
				}
				//$this->token_str = $op;
				return $this->token = $op;
			}

			if ($c == '+' || $c == '-' || $c == '*' || $c == '/' || $c == '%' || $c == '^') {
				$op = $c;
				$c = $this->read();
				if ($c == '=') {
					$c = $this->read();
					$op .= '=';
				}
				//$this->token_str = $op;
				return $this->token = $op;
			}

			if ($c == '.') {
				if (($c = $this->read()) != '+') {
					//$this->token_str = '.';
					return $this->token = '.';
				} elseif (($c = $this->read()) != '=') {
					//$this->token_str = '.+';
					return $this->token = '.+';
				} else {
					$c = $this->read();
					//$this->token_str = '.+=';
					return $this->token = '.+=';
				}
			}

			if ($c == '&' || $c == '|') {
				$op = $c;
				if (($c = $this->read()) == $op) {
					$c = $this->read();
					//$this->token_str = $op . $op;
					return $this->token = $op . $op;
				}
			}

			if ($c == ':') {
				$s = '';

				$c = $this->read();
				if ($c == ':') {
					$c = $this->read();
					if ($c == ':') {
						$this->read();
						return $this->token = ':::';
					}
					return $this->token = '::';
				}
				if (ctype_alpha($c)) {
					while (ctype_alpha($c)) {
						$s .= $c;
						$c = $this->read();
					}
					if (array_key_exists($s, $this->reserved)) {
						return $this->token = ':' . $s;
					} else {
						//throw new KwartzScannerError("invalid identifier.", $this);
						$this->next_token = 'name';
						$this->next_token_str = $s;
						return $this->token = ':';
					}
				}
				return $this->token = ':';
			}
			
			if ($c == '[') {
				$c = $this->read();
				if ($c == ':') {
					$this->read();
					return $this->token = '[:';
				}
				return $this->token = '[';
			}

			switch ($c) {
			case ']':
			case '{':
			case '}':
			case '(':
			case ')':
			case '$':
			case '?':
			case ',':
			case ';':
			case '@':
			case '\\':
			case '`':
			case '~':
				$this->token = $c;
				$this->read();
				return $this->token;
			}

			throw new KwartzScannerException("'$c': invalid char.", $this);
			//$this->read();
			//return $c;
		}

		function scan_all() {
			$s = '';
			while (($this->scan()) != NULL) {
				switch ($this->token) {
				case 'string':
					$s .= kwartz_inspect_str($this->token_str);
					break;
				case 'integer':
				case 'float':
				case 'name':
					$s .= $this->token_str;
					break;
				default:
					$s .= $this->token;
					break;
				}
				$s .= "\n";
			}
			return $s;
		}

	}  // end of class KwartzScanner

// }  // end of namespace Kwartz
?>