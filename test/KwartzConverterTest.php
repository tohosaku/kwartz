<?php

/// $Rev$
/// $Release$
/// $Copyright$


// you need to install PHPUnit2 by 'sudo pear install --alldeps PHPUnit2'
// see http://www.phpunit.de/pocket_guide/2.3/en/installation.html

require_once 'KwartzTest.inc';

require_once 'Kwartz/KwartzParser.php';
require_once 'Kwartz/KwartzConverter.php';
require_once 'Kwartz/KwartzTranslator.php';
require_once 'Kwartz/Binding/Php.php';


class KwartzConverterTest_ extends PHPUnit2_Framework_TestCase {

    var $name;
    var $title;
    var $properties;
    var $desc;
    var $pdata;
    var $plogic;
    var $expected;
    var $excpetion;
    var $message;

    function _test() {
        $pattern = '/\{\{\*|\*\}\}/';
        $pdata    = preg_replace($pattern, '', $this->pdata);
        $plogic   = preg_replace($pattern, '', $this->plogic);
        $expected = preg_replace($pattern, '', $this->expected);
        $properties = array();
        if ($this->properties) {
            foreach ($this->properties as $key => $val) {
                if ($key[0] == ":") $key = substr($key, 1);
                $properties[$key] = $val;
            }
        } else {
            $properties = array();
        }
        //
        $parser = new KwartzCssStyleParser();
        $rulesets = $parser->parse($plogic);
        $handler = new KwartzPhpHandler($rulesets, $properties);
        $converter = new KwartzTextConverter($handler, $properties);
        $stmt_list = $converter->convert($pdata);
        //
        $buf = array();
        foreach ($stmt_list as $stmt) {
            $buf[] = $stmt->_inspect();
        }
        $actual = join($buf);
        kwartz_assert_text_equals($expected, $actual, $this->name);
    }

}


$testdata = kwartz_load_testdata(__FILE__);
$testdata = kwartz_select_testdata($testdata, 'php');

//var_export($testdata);  //exit(0);

kwartz_define_testmethods($testdata, 'KwartzConverterTest');


?>