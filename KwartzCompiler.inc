<?php

###
### KwartzCompiler.inc
###

require_once('KwartzScanner.inc');
require_once('KwartzParser.inc');
require_once('KwartzConverter.inc');
require_once('KwartzTranslator.inc');


//namespace Kwartz {

	class KwartzCompiler {
		private $pdata_str;
		private $plogic_cde;
		
		function __construct($pdata_str=NULL, $plogic_code=NULL) {
			$this->pdata_str = $pdata_str;
			$this->plogic_code = $plogic_code;
		}
		
		function compile() {
			## convert presentation data into block
			$pdata_block = NULL;
			if ($this->pdata_str) {
				$converter = new KwartzConverter($this->pdata_str);
				$pdata_block = $converter->convert();
			}
			
			## convert presentation logic code into block
			$plogic_block = NULL;
			if ($this->plogic_code) {
				$scanner = new KwartzScanner($this->plogic_code);
				$parser  = new KwartzParser($scanner);
				$plogic_block = $parser->parse();
			}
			
			## merge blocks and create a new block
			if (! ($pdata_block || $plogic_block)) {
				return NULL;
			}
			if ($pdata_block && $plogic_block) {
				$stmt_list = array_merge($pdata_block->statements(), $plogic_block->statements());
				$block = new KwartzBlockStatement($stmt_list);
			} else {
				$block = $pdata_block ? $pdata_block : $plogic_block;
			}
			
			## translate block into PHP code
			$translator = new KwartzPhpTranslator($block);
			$code = $translator->translate();
			
			return $code;
		}
	
	}

//}  // end of namespace Kwartz


?>