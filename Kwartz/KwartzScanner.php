<?php
// vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:

// $Id$

require_once('Kwartz/KwartzException.php');
require_once('Kwartz/KwartzUtility.php');

//namespace Kwartz {

class KwartzScanError extends KwartzError {
    function __construct($msg, $linenum=NULL, $filename=NULL) {
        parent::__construct($msg, $linenum, $filename);
    }
}

define('EOF', -1);


/**
 *  scanner which is used by parser.
 *
 *  ex.
 *     $input = "if ($x > 0) { echo $x; }";
 *     $scanner = new Scanner($input);
 *     while (($token = $scanner->scan()) != NULL) {
 *        $token_str = $scanner->token_str();
 *        echo "token='$token', token_str='$token_str'\n";
 *     }
 */
class KwartzScanner {
    private $toppings;
    private $filename;
    private $input;			// string
    private $index;
    private $char;
    private $length;
    private $linenum = 1;
    private $token;
    private $token_str;
    private $next_token;
    private $next_token_str;
    
    static $reserved = array(
        ':if'	   => ':if',
        ':elseif'  => ':elseif',
        ':elsif'   => ':elseif',
        ':else'	   => ':else',
        ':foreach' => ':foreach',
        //':for'   => ':for',
        ':while'   => ':while',
        ':print'   => ':print',
        ':macro'   => ':macro',
        ':expand'  => ':expand',
        ':elem'	   => ':element',
        ':element' => ':element',
        'true'	   => 'true',
        'false'	   => 'false',
        'null'	   => 'null',
        'nil'	   => 'null',
        'empty'	   => 'empty',
        ':set'	   => ':set',
        ':end'	   => ':end',
        ':load'	   => ':load',
        
        'if'	   => 'if',
        'elseif'   => 'elseif',
        //'elsif'  => 'elseif',
        'else'	   => 'else',
        'foreach'  => 'foreach',
        //'for'	   => 'for',
        'while'	   => 'while',
        'echo'	   => 'echo',
        //'print'   => 'print',
        'macro'	   => 'macro',
        'expand'   => 'expand',
        'elem'	   => 'element',
        'element'  => 'element',
        'TRUE'	   => 'true',
        'FALSE'	   => 'false',
        'NULL'	   => 'null',
        'nil'	   => 'null',
        'load'	   => 'load',
        );
    
    //const EOF = -1;
    
    
    function __construct($input, $toppings=NULL) {
        $this->input = $input;
        $this->toppings = $toppings ? $toppings : array();
        $this->_initialize();
    }
    
    function topping($name) {
        if (array_key_exists($name, $this->toppings)) {
            return $this->toppings[$name];
        }
        return NULL;
    }
    
    function reset($input, $linenum=NULL) {
        $this->input = $input;
        $this->_initialize($linenum);
    }
    
    function _initialize($linenum=NULL) {
        $this->length  = strlen($this->input);
        $this->index   = -1;
        $this->char    = NULL;
        $this->linenum = $linenum ? $linenum : 1;
        $this->token          = NULL;
        $this->token_str      = NULL;
        $this->next_token     = NULL;
        $this->next_token_str = NULL;
        $this->filename = $this->topping('filename');
        
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
    
    function filename() {
        return $this->filename;
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
        
        // ignore spaces and comments
        while (1) {
            // ignore spaces
            while (ctype_space($c)) {
                $c = $this->read();
            }
            
            // ignore comments
            if ($c == '#') {
                while (($c = $this->read()) != EOF && $c != "\n") {
                    // nothing
                }
                if ($c == EOF) { break; }
                continue;
            }
            break;
        }
        
        //
        if ($c == EOF) {
            return $this->token = NULL;
        }
        
        // reserved-word or name
        if (ctype_alpha($c) || $c == '_') {
            $s = $c;
            while ( ($c = $this->read()) != EOF && (ctype_alnum($c) || $c == '_') ) {
                $s .= $c;
            }
            if (array_key_exists($s, KwartzScanner::$reserved)) {
                $this->token = KwartzScanner::$reserved[$s];
                //$this->token_str = $s;
            } else {
                $this->token = 'name';
                $this->token_str = $s;
            }
            return $this->token;
        }
        
        // integer or float
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
                $s .= $c;
                while (($c = $this->read()) != EOF && (ctype_alnum($c) || $c == '_')) {
                    $s .= $c;
                }
                throw new KwartzScanError("'$s': invalid number or token.", $this->linenum, $this->filename);
            }
            $this->token_str = $s;
            return $this->token;
        }
        
        // string
        if ($c == '"') {
            $s = '';
            $start_linenum = $this->linenum;
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
                throw new KwartzScanError("string is not closed.", $start_linenum, $this->filename);
            }
            assert($c == '"');
            $this->token_str = $s;
            $this->read();
            return $this->token = 'string';
        }
        
        if ($c == "'") {
            $this->token_str = '';
            $start_linenum = $this->linenum;
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
                throw new KwartzScanError("string is not closed.", $start_linenum, $this->filename);
            }
            assert($c == "'");
            $this->read();
            return $this->token = 'string';
        }
        
        if ($c == '<') {
            $c = $this->read();
            if ($c == '=') {
                $this->read();
                return $this->token = '<=';
            } elseif ($c == '?' || $c == '%') {
                $s = '<' . $c;
                while (($c = $this->read()) != EOF && $c != "\n") {
                    $s .= $c;
                }
                $this->token_str = $s;
                return $this->token = ':rawcode';
            }
            return $this->token = '<';
        }
        
        if ($c == '>' || $c == '!') {
            $op = $c;
            $c = $this->read();
            if ($c == '=') {
                $c = $this->read();
                $op .= '=';
            }
            return $this->token = $op;
        }
        
        if ($c == '=') {
            $op = $c;
            $c = $this->read();
            if ($c == '=' || $c == '>') {
                $c = $this->read();
                $op .= '=';
            }
            return $this->token = $op;
        }
        
        if ($c == '-') {
            $c = $this->read();
            if ($c == '>') {
                $this->read();  return $this->token = '->';
            } elseif ($c == '=') {
                $this->read();  return $this->token = '-=';
            } elseif ($c == '-') {
                $this->read();  return $this->token = '--';
            }
            return $this->token = '-';
        }
        
        if ($c == '+') {
            $c = $this->read();
            if ($c == '=') {
                $this->read();  return $this->token = '+=';
            } elseif ($c == '+') {
                $this->read();  return $this->token = '++';
            }
            return $this->token = '+';
        }
        
        if ($c == '*' || $c == '/' || $c == '%' || $c == '^') {
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
            $c = $this->read();
            if ($c == '=') {
                $c = $this->read();
                return $this->token = '.=';
            } else if ($c == '+') {
                if (($c = $this->read()) == '=') {
                    $c = $this->read();
                    return $this->token = '.+=';
                }
                return $this->token = '.+';
            }
            return $this->token = '.';
        }
        
        if ($c == '&' || $c == '|') {
            $op = $c;
            if (($c = $this->read()) == $op) {
                $c = $this->read();
                return $this->token = $op . $op;
            }
            //return $c;
            throw new KwartzScannerError("'{$op}': invalid character.", $this->linenum, $this->filename);
        }
        
        if ($c == ':') {
            $c = $this->read();
            if ($c == ':') {
                // '::'
                $c = $this->read();
                if ($c != ':') {
                    return $this->token = '::';
                }
                // ':::'
                $s = '';
                while (($c = $this->read()) != EOF && $c != "\n") {
                    $s .= $c;
                }
                $this->token_str = $s;
                return $this->token = ':::';	// rawcode
            }
            
            // ':'
            if (! ctype_alpha($c)) {
                return $this->token = ':';
            }
            
            // ':foreach', ':if', ':end', ...
            $s = ':';
            while (ctype_alpha($c)) {
                $s .= $c;
                $c = $this->read();
            }
            if (array_key_exists($s, KwartzScanner::$reserved)) {
                return $this->token = KwartzScanner::$reserved[$s];
            }
            
            // ':' + reserved word or ':' + variable
            $word = substr($s, 1);
            if (array_key_exists($word, KwartzScanner::$reserved)) {	// ':' + reserved word
                $this->next_token = KwartzScanner::$reserved[$word];
                //$this->next_token_str = $word;
            } else {							// ':' + variable
                $this->next_token = 'name';
                $this->next_token_str = $word;
            }
            return $this->token = ':';
        }
        
        if ($c == '[') {
            $c = $this->read();
            if ($c != ':') {
                return $this->token = '[';
            }
            $this->read();
            return $this->token = '[:';
        }
        
        if ($c == '@') {
            $s = '';
            while ( ($c = $this->read()) != EOF && (ctype_alnum($c) || $c == '_') ) {
                $s .= $c;
            }
            if ($s == '') {
                $msg = "'@' needs macro-name.";
                throw new KwartzScannerError($msg, $this->linenum, $this->filename);
            }
            $this->token_str = $s;
            return $this->token = '@';
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
          //case '\\':
          //case '`':
          //case '~':
            $this->token = $c;
            $this->read();
            return $this->token;
        }
        
        throw new KwartzScanError("'$c': invalid char.", $this->linenum, $this->filename);
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