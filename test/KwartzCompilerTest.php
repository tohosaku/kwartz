<?php

###
### KwartzCompilerTest.php
###

require_once('PHPUnit.php');
require_once('Kwartz/KwartzCompiler.php');

class KwartzCompilerTest extends PHPUnit_TestCase {

	function __construct($name) {
		$this->PHPUnit_TestCase($name);
	}


	function __test($pdata, $plogic, $expected, $lang, $flag_test, $toppings, $flag_escape) {
		//if (! $flag_test) { return; }
		$pdata    = preg_replace('/^\t\t/m', '', $pdata);
		$pdata    = preg_replace('/^\n/',    '', $pdata);
		$plogic   = preg_replace('/^\t\t/m', '', $plogic);
		$plogic   = preg_replace('/^\n/',    '', $plogic);
		$expected = preg_replace('/^\t\t/m', '', $expected);
		$expected = preg_replace('/^\n/',    '', $expected);
		if ($toppings == NULL) {
			$toppings = array();
		}
		if (!array_key_exists('indent_width', $toppings)) {
			$toppings['indent_width'] = 2;
		}
		$compiler = new KwartzCompiler($pdata, $plogic, $lang, $flag_escape, $toppings);
		$code = $compiler->compile();
		$actual = $code;
		if ($flag_test) {
			if ($expected != $actual) {
				$f = fopen('.tmp.expected', "w");
				fwrite($f, $expected);
				fclose($f);
				$f = fopen('.tmp.actual', "w");
				fwrite($f, $actual);
				fclose($f);
				system('diff -u .tmp.expected .tmp.actual');
				unlink('.tmp.expected');
				unlink('.tmp.actual');
			}
			$this->assertEquals($expected, $actual);
		} else {
			#echo "\n------\n";
			#echo kwartz_inspect_str($expected);
			#echo "\n------\n";
			#echo kwartz_inspect_str($actual);
		}
		return $compiler;
	}
	
	function _test($pdata, $plogic, $expected, $lang, $flag_test=TRUE, $toppings=NULL) {
		return $this->__test($pdata, $plogic, $expected, $lang, $flag_test, $toppings, FALSE);
	}
	
	function _test_escape($pdata, $plogic, $expected, $lang, $flag_test=TRUE, $toppings=NULL) {
		return $this->__test($pdata, $plogic, $expected, $lang, $flag_test, $toppings, TRUE);
	}

	
	
	const pdata_compile1 = '
		<html>
		 <body>
		  <table>
		   <tr class="odd" id="mark:user" kd="attr:class:klass">
		    <td id="value:user[\'name\']">foo</td>
		    <td id="value:user[\'mail\']">foo@mail.com</td>
		   </tr>
		   <tr class="even" id="dummy:d1">
		    <td>bar</td>
		    <td>bar@mail.com</td>
		   </tr>
		  </table>
		 </body>
		</html>
		';

	const pdata_compile1_php = '
		<html>
		 <body>
		  <table>
		   <tr class="odd" kd:php="mark(user);attr(\'class\'=>$klass)">
		    <td kd:php="value($user[\'name\'])">foo</td>
		    <td kd:php="value($user[\'mail\'])">foo@mail.com</td>
		   </tr>
		   <tr class="even" kd:php="dummy(d1)">
		    <td>bar</td>
		    <td>bar@mail.com</td>
		   </tr>
		  </table>
		 </body>
		</html>
		';

	const plogic_compile1 = '
		:element(user)
		  :set(i = 0)
		  :foreach(user = user_list)
		    :set(i += 1)
		    :set(klass = i%2==0 ? \'even\' : \'odd\')
		    @stag
		    @cont
		    @etag
		  :end
		:end
	';

	const plogic_compile1_php = '
		element user {
		  $i = 0;
		  foreach ($user_list as $user) {
		    $i += 1;
		    $klass = $i % 2 == 0 ? \'even\' : \'odd\';
		    @stag;
		    @cont;
		    @etag;
		  }
		}
		';
	
	const expected_compile1_php = '
		<html>
		 <body>
		  <table>
		<?php $i = 0; ?>
		<?php foreach ($user_list as $user) { ?>
		<?php   $i += 1; ?>
		<?php   $klass = $i % 2 == 0 ? "even" : "odd"; ?>
		   <tr class="<?php echo $klass; ?>">
		    <td><?php echo $user["name"]; ?></td>
		    <td><?php echo $user["mail"]; ?></td>
		   </tr>
		<?php } ?>
		  </table>
		 </body>
		</html>
		';

	const expected_compile1_eruby = '
		<html>
		 <body>
		  <table>
		<% i = 0 %>
		<% for user in user_list do %>
		<%   i += 1 %>
		<%   klass = i % 2 == 0 ? "even" : "odd" %>
		   <tr class="<%= klass %>">
		    <td><%= user["name"] %></td>
		    <td><%= user["mail"] %></td>
		   </tr>
		<% end %>
		  </table>
		 </body>
		</html>
		';

	const expected_compile1_jstl11 = '
		<html>
		 <body>
		  <table>
		<c:set var="i" value="0"/>
		<c:forEach var="user" items="${user_list}">
		  <c:set var="i" value="${i + 1}"/>
		  <c:set var="klass" value="${i % 2 == 0 ? \'even\' : \'odd\'}"/>
		   <tr class="<c:out value="${klass}" escapeXml="false"/>">
		    <td><c:out value="${user[\'name\']}" escapeXml="false"/></td>
		    <td><c:out value="${user[\'mail\']}" escapeXml="false"/></td>
		   </tr>
		</c:forEach>
		  </table>
		 </body>
		</html>
		';

	const expected_compile1_jstl10 = '
		<html>
		 <body>
		  <table>
		<c:set var="i" value="0"/>
		<c:forEach var="user" items="${user_list}">
		  <c:set var="i" value="${i + 1}"/>
		  <c:choose>
		    <c:when test="${i % 2 == 0}">
		      <c:set var="klass" value="even"/>
		    </c:when>
		    <c:otherwise>
		      <c:set var="klass" value="odd"/>
		    </c:otherwise>
		  </c:choose>
		   <tr class="<c:out value="${klass}" escapeXml="false"/>">
		    <td><c:out value="${user[\'name\']}" escapeXml="false"/></td>
		    <td><c:out value="${user[\'mail\']}" escapeXml="false"/></td>
		   </tr>
		</c:forEach>
		  </table>
		 </body>
		</html>
		';


	function test_compile1_php() {
		$pdata      = KwartzCompilerTest::pdata_compile1;
		$pdata_php  = KwartzCompilerTest::pdata_compile1_php;
		$plogic     = KwartzCompilerTest::plogic_compile1;
		$plogic_php = KwartzCompilerTest::plogic_compile1_php;
		$expected_php   = KwartzCompilerTest::expected_compile1_php;

		$this->_test($pdata,     $plogic,     $expected_php, 'php');
		$this->_test($pdata,     $plogic_php, $expected_php, 'php');
		$this->_test($pdata_php, $plogic,     $expected_php, 'php');
		$this->_test($pdata_php, $plogic_php, $expected_php, 'php');
	}


	function test_compile1_eruby() {
		$pdata      = KwartzCompilerTest::pdata_compile1;
		$pdata_php  = KwartzCompilerTest::pdata_compile1_php;
		$plogic     = KwartzCompilerTest::plogic_compile1;
		$plogic_php = KwartzCompilerTest::plogic_compile1_php;
		$expected_eruby   = KwartzCompilerTest::expected_compile1_eruby;

		$this->_test($pdata,     $plogic,     $expected_eruby, 'eruby');
		$this->_test($pdata,     $plogic_php, $expected_eruby, 'eruby');
		$this->_test($pdata_php, $plogic,     $expected_eruby, 'eruby');
		$this->_test($pdata_php, $plogic_php, $expected_eruby, 'eruby');
	}

	function test_compile1_jstl11() {
		$pdata          = KwartzCompilerTest::pdata_compile1;
		$pdata_php      = KwartzCompilerTest::pdata_compile1_php;
		$plogic         = KwartzCompilerTest::plogic_compile1;
		$plogic_php     = KwartzCompilerTest::plogic_compile1_php;
		$expected_jstl11  = KwartzCompilerTest::expected_compile1_jstl11;

		$this->_test($pdata,     $plogic,     $expected_jstl11, 'jstl11');
		$this->_test($pdata,     $plogic_php, $expected_jstl11, 'jstl11');
		$this->_test($pdata_php, $plogic,     $expected_jstl11, 'jstl11');
		$this->_test($pdata_php, $plogic_php, $expected_jstl11, 'jstl11');
	}

	function test_compile1_jstl10() {
		$pdata          = KwartzCompilerTest::pdata_compile1;
		$pdata_php      = KwartzCompilerTest::pdata_compile1_php;
		$plogic         = KwartzCompilerTest::plogic_compile1;
		$plogic_php     = KwartzCompilerTest::plogic_compile1_php;
		$expected_jstl10 = KwartzCompilerTest::expected_compile1_jstl10;

		$this->_test($pdata,     $plogic,     $expected_jstl10, 'jstl10');
		$this->_test($pdata,     $plogic_php, $expected_jstl10, 'jstl10');
		$this->_test($pdata_php, $plogic,     $expected_jstl10, 'jstl10');
		$this->_test($pdata_php, $plogic_php, $expected_jstl10, 'jstl10');
	}



	## --------------------


	const pdata_compile2 = '
		<a href="#{url}#" id="mark:link">next page</a>
		';

	const pdata_compile2_php = '
		<a href="@{$url}@" kd:php="mark(link)">next page</a>
		';

	const plogic_compile2 = '
		:element(link)
		  :if (url != null)
		    @stag
		    @cont
		    @etag
		  :else
		    @cont
		  :end
		:end
		';

	const plogic_compile2_php = '
		element link {
		  if ($url != NULL) {
		    @stag;
		    @cont;
		    @etag;
		  } else {
		    @cont;
		  }
		}
		';

	const expected_compile2_php = '
		<?php if ($url != NULL) { ?>
		<a href="<?php echo $url; ?>">next page</a>
		<?php } else { ?>
		next page<?php } ?>
		';

	const expected_compile2_eruby = '
		<% if url != nil then %>
		<a href="<%= url %>">next page</a>
		<% else %>
		next page<% end %>
		';

	const expected_compile2_jstl11 = '
		<c:choose>
		  <c:when test="${url != null}">
		<a href="<c:out value="${url}" escapeXml="false"/>">next page</a>
		  </c:when>
		  <c:otherwise>
		next page</c:otherwise>
		</c:choose>
		';

	const expected_compile2_jstl10 =	// equals to expected_compile2_jstl11
		'
		<c:choose>
		  <c:when test="${url != null}">
		<a href="<c:out value="${url}" escapeXml="false"/>">next page</a>
		  </c:when>
		  <c:otherwise>
		next page</c:otherwise>
		</c:choose>
		';

	function test_compile2_php() {
		$pdata      = KwartzCompilerTest::pdata_compile2;
		$pdata_php  = KwartzCompilerTest::pdata_compile2_php;
		$plogic     = KwartzCompilerTest::plogic_compile2;
		$plogic_php = KwartzCompilerTest::plogic_compile2_php;
		$expected_php = KwartzCompilerTest::expected_compile2_php;

		$this->_test($pdata,     $plogic,     $expected_php, 'php');
		$this->_test($pdata,     $plogic_php, $expected_php, 'php');
		$this->_test($pdata_php, $plogic,     $expected_php, 'php');
		$this->_test($pdata_php, $plogic_php, $expected_php, 'php');
	}

	function test_compile2_eruby() {
		$pdata      = KwartzCompilerTest::pdata_compile2;
		$pdata_php  = KwartzCompilerTest::pdata_compile2_php;
		$plogic     = KwartzCompilerTest::plogic_compile2;
		$plogic_php = KwartzCompilerTest::plogic_compile2_php;
		$expected_eruby = KwartzCompilerTest::expected_compile2_eruby;

		$this->_test($pdata,     $plogic,     $expected_eruby, 'eruby');
		$this->_test($pdata,     $plogic_php, $expected_eruby, 'eruby');
		$this->_test($pdata_php, $plogic,     $expected_eruby, 'eruby');
		$this->_test($pdata_php, $plogic_php, $expected_eruby, 'eruby');
	}

	function test_compile2_jstl11() {
		$pdata          = KwartzCompilerTest::pdata_compile2;
		$pdata_php      = KwartzCompilerTest::pdata_compile2_php;
		$plogic         = KwartzCompilerTest::plogic_compile2;
		$plogic_php     = KwartzCompilerTest::plogic_compile2_php;
		$expected_jstl11  = KwartzCompilerTest::expected_compile2_jstl11;

		$this->_test($pdata,     $plogic,     $expected_jstl11, 'jstl11');
		$this->_test($pdata,     $plogic_php, $expected_jstl11, 'jstl11');
		$this->_test($pdata_php, $plogic,     $expected_jstl11, 'jstl11');
		$this->_test($pdata_php, $plogic_php, $expected_jstl11, 'jstl11');
	}

	function test_compile2_jstl10() {
		$pdata          = KwartzCompilerTest::pdata_compile2;
		$pdata_php      = KwartzCompilerTest::pdata_compile2_php;
		$plogic         = KwartzCompilerTest::plogic_compile2;
		$plogic_php     = KwartzCompilerTest::plogic_compile2_php;
		$expected_jstl10 = KwartzCompilerTest::expected_compile2_jstl10;

		$this->_test($pdata,     $plogic,     $expected_jstl10, 'jstl10');
		$this->_test($pdata,     $plogic_php, $expected_jstl10, 'jstl10');
		$this->_test($pdata_php, $plogic,     $expected_jstl10, 'jstl10');
		$this->_test($pdata_php, $plogic_php, $expected_jstl10, 'jstl10');
	}



	## --------------------

	const pdata_compile3 = '
		<table cellpadding="2" summary="">
		  <caption>
		    <i id="value:month">Jan</i>&nbsp;<i id="value:year">20XX</i>
		  </caption>
		  <thead>
		    <tr bgcolor="#CCCCCC">
		      <th><span class="holiday">S</span></th>
		      <th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th>
		    </tr>
		  </thead>
		  <tbody>
		    <tr id="mark:week">
		      <td><span id="mark:day" class="holiday">&nbsp;</span></td>
		      <td id="dummy:d1">&nbsp;</td>
		      <td id="dummy:d2">1</td>
		      <td id="dummy:d3">2</td>
		      <td id="dummy:d4">3</td>
		      <td id="dummy:d5">4</td>
		      <td id="dummy:d6">5</td>
		    </tr>
		    <tr id="dummy:w1">
		      <td><span class="holiday">6</span></td>
		      <td>7</td><td>8</td><td>9</td>
		      <td>10</td><td>11</td><td>12</td>
		    </tr>
		    <tr id="dummy:w2">
		      <td><span class="holiday">13</span></td>
		      <td>14</td><td>15</td><td>16</td>
		      <td>17</td><td>18</td><td>19</td>
		    </tr>
		    <tr id="dummy:w3">
		      <td><span class="holiday">20</span></td>
		      <td>21</td><td>22</td><td>23</td>
		      <td>24</td><td>25</td><td>26</td>
		    </tr>
		    <tr id="dummy:w4">
		      <td><span class="holiday">27</span></td>
		      <td>28</td><td>29</td><td>30</td>
		      <td>31</td><td>&nbsp;</td><td>&nbsp;</td>
		    </tr>
		  </tbody>
		</table>
		&nbsp;
		';

	const plogic_compile3 = '
		:element(week)
		
		  :set(day = \'&nbsp;\')
		  :set(wday = 1)
		  :while(wday < first_weekday)
		    :if(wday == 1)
		      @stag
		    :end
		    @cont
		    :set(wday += 1)
		  :end
		
		  :set(day = 0)
		  :set(wday -= 1)
		  :while(day < num_days)
		    :set(day += 1)
		    :set(wday = wday % 7 + 1)
		    :if(wday == 1)
		      @stag
		    :end
		    @cont
		    :if(wday == 7)
		      @etag
		    :end
		  :end
		
		  :if(wday != 7)
		    :set(day = \'&nbsp;\')
		    :while(wday != 6)
		      :set(wday += 1)
		      @cont
		    :end
		    @etag
		  :end
		
		:end
		
		:element(day)
		  :if(wday == 1)
		    @stag
		    :print(day)
		    @etag
		  :else
		    :print(day)
		  :end
		:end
		';

	const pdata_compile3_php = '
		<table cellpadding="2" summary="">
		  <caption>
		    <i kd:php="value($month)">Jan</i>&nbsp;<i kd:php="value($year)">20XX</i>
		  </caption>
		  <thead>
		    <tr bgcolor="#CCCCCC">
		      <th><span class="holiday">S</span></th>
		      <th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th>
		    </tr>
		  </thead>
		  <tbody>
		    <tr kd:php="mark(week)">
		      <td><span kd:php="mark(day)" class="holiday">&nbsp;</span></td>
		      <td id="dummy:d1">&nbsp;</td>
		      <td id="dummy:d2">1</td>
		      <td id="dummy:d3">2</td>
		      <td id="dummy:d4">3</td>
		      <td id="dummy:d5">4</td>
		      <td id="dummy:d6">5</td>
		    </tr>
		    <tr kd:php="dummy(w1)">
		      <td><span class="holiday">6</span></td>
		      <td>7</td><td>8</td><td>9</td>
		      <td>10</td><td>11</td><td>12</td>
		    </tr>
		    <tr kd:php="dummy(w2)">
		      <td><span class="holiday">13</span></td>
		      <td>14</td><td>15</td><td>16</td>
		      <td>17</td><td>18</td><td>19</td>
		    </tr>
		    <tr kd:php="dummy(w3)">
		      <td><span class="holiday">20</span></td>
		      <td>21</td><td>22</td><td>23</td>
		      <td>24</td><td>25</td><td>26</td>
		    </tr>
		    <tr kd:php="dummy(w4)">
		      <td><span class="holiday">27</span></td>
		      <td>28</td><td>29</td><td>30</td>
		      <td>31</td><td>&nbsp;</td><td>&nbsp;</td>
		    </tr>
		  </tbody>
		</table>
		&nbsp;
		';

	const plogic_compile3_php = '
		element week {
		
		    $day = \'&nbsp;\';
		    $wday = 1;
		    while ($wday < $first_weekday) {
		        if ($wday == 1) {
		            @stag;
		        }
		        @cont;
		        $wday += 1;
		    }
		
		    $day = 0;
		    $wday -= 1;
		    while ($day < $num_days) {
		        $day += 1;
		        $wday = $wday % 7 + 1;
		        if ($wday == 1) {
		            @stag;
		        }
		        @cont;
		        if ($wday == 7) {
		            @etag;
		        }
		    }
		
		    if ($wday != 7) {
		        $day = \'&nbsp;\';
		        while ($wday != 6) {
		            $wday += 1;
		            @cont;
		        }
		        @etag;
		    }
		
		}
		
		element day {
		    if ($wday == 1) {
			@stag
			echo $day;
			@etag;
		    } else {
			echo $day;
		    }
		}
		';

	const expected_compile3_php = '
		<table cellpadding="2" summary="">
		  <caption>
		    <i><?php echo $month; ?></i>&nbsp;<i><?php echo $year; ?></i>
		  </caption>
		  <thead>
		    <tr bgcolor="#CCCCCC">
		      <th><span class="holiday">S</span></th>
		      <th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th>
		    </tr>
		  </thead>
		  <tbody>
		<?php $day = "&nbsp;"; ?>
		<?php $wday = 1; ?>
		<?php while ($wday < $first_weekday) { ?>
		<?php   if ($wday == 1) { ?>
		    <tr>
		<?php   } ?>
		      <td><?php if ($wday == 1) { ?>
		<span class="holiday"><?php echo $day; ?></span><?php } else { ?>
		<?php echo $day; ?><?php } ?>
		</td>
		<?php   $wday += 1; ?>
		<?php } ?>
		<?php $day = 0; ?>
		<?php $wday -= 1; ?>
		<?php while ($day < $num_days) { ?>
		<?php   $day += 1; ?>
		<?php   $wday = $wday % 7 + 1; ?>
		<?php   if ($wday == 1) { ?>
		    <tr>
		<?php   } ?>
		      <td><?php if ($wday == 1) { ?>
		<span class="holiday"><?php echo $day; ?></span><?php } else { ?>
		<?php echo $day; ?><?php } ?>
		</td>
		<?php   if ($wday == 7) { ?>
		    </tr>
		<?php   } ?>
		<?php } ?>
		<?php if ($wday != 7) { ?>
		<?php   $day = "&nbsp;"; ?>
		<?php   while ($wday != 6) { ?>
		<?php     $wday += 1; ?>
		      <td><?php if ($wday == 1) { ?>
		<span class="holiday"><?php echo $day; ?></span><?php } else { ?>
		<?php echo $day; ?><?php } ?>
		</td>
		<?php   } ?>
		    </tr>
		<?php } ?>
		  </tbody>
		</table>
		&nbsp;
		';

	const expected_compile3_eruby = '
		<table cellpadding="2" summary="">
		  <caption>
		    <i><%= month %></i>&nbsp;<i><%= year %></i>
		  </caption>
		  <thead>
		    <tr bgcolor="#CCCCCC">
		      <th><span class="holiday">S</span></th>
		      <th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th>
		    </tr>
		  </thead>
		  <tbody>
		<% day = "&nbsp;" %>
		<% wday = 1 %>
		<% while wday < first_weekday do %>
		<%   if wday == 1 then %>
		    <tr>
		<%   end %>
		      <td><% if wday == 1 then %>
		<span class="holiday"><%= day %></span><% else %>
		<%= day %><% end %>
		</td>
		<%   wday += 1 %>
		<% end %>
		<% day = 0 %>
		<% wday -= 1 %>
		<% while day < num_days do %>
		<%   day += 1 %>
		<%   wday = wday % 7 + 1 %>
		<%   if wday == 1 then %>
		    <tr>
		<%   end %>
		      <td><% if wday == 1 then %>
		<span class="holiday"><%= day %></span><% else %>
		<%= day %><% end %>
		</td>
		<%   if wday == 7 then %>
		    </tr>
		<%   end %>
		<% end %>
		<% if wday != 7 then %>
		<%   day = "&nbsp;" %>
		<%   while wday != 6 do %>
		<%     wday += 1 %>
		      <td><% if wday == 1 then %>
		<span class="holiday"><%= day %></span><% else %>
		<%= day %><% end %>
		</td>
		<%   end %>
		    </tr>
		<% end %>
		  </tbody>
		</table>
		&nbsp;
		';


	function test_compile3_php() {
		$pdata        = KwartzCompilerTest::pdata_compile3;
		$plogic       = KwartzCompilerTest::plogic_compile3;
		$pdata_php    = KwartzCompilerTest::pdata_compile3_php;
		$plogic_php   = KwartzCompilerTest::plogic_compile3_php;
		$expected_php = KwartzCompilerTest::expected_compile3_php;

		$this->_test($pdata,     $plogic,     $expected_php, 'php');
		$this->_test($pdata,     $plogic_php, $expected_php, 'php');
		$this->_test($pdata_php, $plogic,     $expected_php, 'php');
		$this->_test($pdata_php, $plogic_php, $expected_php, 'php');
	}

	function test_compile3_eruby() {
		$pdata          = KwartzCompilerTest::pdata_compile3;
		$plogic         = KwartzCompilerTest::plogic_compile3;
		$pdata_php      = KwartzCompilerTest::pdata_compile3_php;
		$plogic_php     = KwartzCompilerTest::plogic_compile3_php;
		$expected_eruby = KwartzCompilerTest::expected_compile3_eruby;

		$this->_test($pdata,     $plogic,     $expected_eruby, 'eruby');
		$this->_test($pdata,     $plogic_php, $expected_eruby, 'eruby');
		$this->_test($pdata_php, $plogic,     $expected_eruby, 'eruby');
		$this->_test($pdata_php, $plogic_php, $expected_eruby, 'eruby');
	}

	#function test_compile3_jstl11() {
	#	# translation error
	#}



	## --------------------
	## newline char
	## --------------------
	function test_newline1_php() {
		$pdata    = preg_replace('/\n/', "\r\n", KwartzCompilerTest::pdata_compile1);
		$plogic   = preg_replace('/\n/', "\r\n", KwartzCompilerTest::plogic_compile1);
		$expected = preg_replace('/\n/', "\r\n", KwartzCompilerTest::expected_compile1_php);
		$this->_test($pdata, $plogic, $expected, 'php');
	}
	function test_newline1_eruby() {
		$pdata    = preg_replace('/\n/', "\r\n", KwartzCompilerTest::pdata_compile1);
		$plogic   = preg_replace('/\n/', "\r\n", KwartzCompilerTest::plogic_compile1);
		$expected = preg_replace('/\n/', "\r\n", KwartzCompilerTest::expected_compile1_eruby);
		$this->_test($pdata, $plogic, $expected, 'eruby');
	}
	function test_newline1_jstl11() {
		$pdata    = preg_replace('/\n/', "\r\n", KwartzCompilerTest::pdata_compile1);
		$plogic   = preg_replace('/\n/', "\r\n", KwartzCompilerTest::plogic_compile1);
		$expected = preg_replace('/\n/', "\r\n", KwartzCompilerTest::expected_compile1_jstl11);
		$this->_test($pdata, $plogic, $expected, 'jstl11');
	}
	function test_newline1_jstl10() {
		$pdata    = preg_replace('/\n/', "\r\n", KwartzCompilerTest::pdata_compile1);
		$plogic   = preg_replace('/\n/', "\r\n", KwartzCompilerTest::plogic_compile1);
		$expected = preg_replace('/\n/', "\r\n", KwartzCompilerTest::expected_compile1_jstl10);
		$this->_test($pdata, $plogic, $expected, 'jstl10');
	}



	## --------------------
	## escape
	## --------------------
	const pdata_escape1 = '
		<html>
		  <body>
		    <div id="mark:mainbody">
		      <div class="error" kd="mark:error">
		        <span kd="value:error_msg">not found.</span>
		      </div>
		      <table kd="mark:user_list">
		        <tbody kd="LOOP:user=user_list">
		          <tr class="odd" kd="attr:class:user_tgl">
		            <td kd="value:user[:name]">foo</td>
		          </tr>
		        </tbody>
		      </table>
		      <p kd="mark:total">total:<span kd="value:user_ctr">10</span></p>
		    </div>
		  </body>
		</html>
		';
	
	const plogic_escape1 = '
		:element(mainbody)
		  :if (flag_error)
		    @element_error
		  :else
		    @element_user_list
		    @element_total
		  :end
		:end
		';
	
	const expected_escape1_php = '
		<html>
		  <body>
		<?php if ($flag_error) { ?>
		      <div class="error">
		<?php echo htmlspecialchars($error_msg); ?>      </div>
		<?php } else { ?>
		      <table>
		        <tbody>
		<?php   $user_ctr = 0; ?>
		<?php   foreach ($user_list as $user) { ?>
		<?php     $user_ctr += 1; ?>
		<?php     $user_tgl = $user_ctr % 2 == 0 ? "even" : "odd"; ?>
		          <tr class="<?php echo htmlspecialchars($user_tgl); ?>">
		            <td><?php echo htmlspecialchars($user[\'name\']); ?></td>
		          </tr>
		<?php   } ?>
		        </tbody>
		      </table>
		      <p>total:<?php echo htmlspecialchars($user_ctr); ?></p>
		<?php } ?>
		  </body>
		</html>
		';

	const expected_escape1_eruby = '
		<html>
		  <body>
		<% if flag_error then %>
		      <div class="error">
		<%= CGI::escapeHTML((error_msg).to_s) %>      </div>
		<% else %>
		      <table>
		        <tbody>
		<%   user_ctr = 0 %>
		<%   for user in user_list do %>
		<%     user_ctr += 1 %>
		<%     user_tgl = user_ctr % 2 == 0 ? "even" : "odd" %>
		          <tr class="<%= CGI::escapeHTML((user_tgl).to_s) %>">
		            <td><%= CGI::escapeHTML((user[:name]).to_s) %></td>
		          </tr>
		<%   end %>
		        </tbody>
		      </table>
		      <p>total:<%= CGI::escapeHTML((user_ctr).to_s) %></p>
		<% end %>
		  </body>
		</html>
		';

	const expected_escape1_jstl11 = '
		<html>
		  <body>
		<c:choose>
		  <c:when test="${flag_error}">
		      <div class="error">
		<c:out value="${error_msg}"/>      </div>
		  </c:when>
		  <c:otherwise>
		      <table>
		        <tbody>
		    <c:set var="user_ctr" value="0"/>
		    <c:forEach var="user" items="${user_list}">
		      <c:set var="user_ctr" value="${user_ctr + 1}"/>
		      <c:set var="user_tgl" value="${user_ctr % 2 == 0 ? \'even\' : \'odd\'}"/>
		          <tr class="<c:out value="${user_tgl}"/>">
		            <td><c:out value="${user[\'name\']}"/></td>
		          </tr>
		    </c:forEach>
		        </tbody>
		      </table>
		      <p>total:<c:out value="${user_ctr}"/></p>
		  </c:otherwise>
		</c:choose>
		  </body>
		</html>
		';

	const expected_escape1_jstl10 = '
		<html>
		  <body>
		<c:choose>
		  <c:when test="${flag_error}">
		      <div class="error">
		<c:out value="${error_msg}"/>      </div>
		  </c:when>
		  <c:otherwise>
		      <table>
		        <tbody>
		    <c:set var="user_ctr" value="0"/>
		    <c:forEach var="user" items="${user_list}">
		      <c:set var="user_ctr" value="${user_ctr + 1}"/>
		      <c:choose>
		        <c:when test="${user_ctr % 2 == 0}">
		          <c:set var="user_tgl" value="even"/>
		        </c:when>
		        <c:otherwise>
		          <c:set var="user_tgl" value="odd"/>
		        </c:otherwise>
		      </c:choose>
		          <tr class="<c:out value="${user_tgl}"/>">
		            <td><c:out value="${user[\'name\']}"/></td>
		          </tr>
		    </c:forEach>
		        </tbody>
		      </table>
		      <p>total:<c:out value="${user_ctr}"/></p>
		  </c:otherwise>
		</c:choose>
		  </body>
		</html>
		';

	function test_escape1_php() {
		$pdata    = KwartzCompilerTest::pdata_escape1;
		$plogic   = KwartzCompilerTest::plogic_escape1;
		$expected = KwartzCompilerTest::expected_escape1_php;
		$this->_test_escape($pdata, $plogic, $expected, 'php');
	}

	function test_escape1_eruby() {
		$pdata    = KwartzCompilerTest::pdata_escape1;
		$plogic   = KwartzCompilerTest::plogic_escape1;
		$expected = KwartzCompilerTest::expected_escape1_eruby;
		$this->_test_escape($pdata, $plogic, $expected, 'eruby');
	}

	function test_escape1_jstl11() {
		$pdata    = KwartzCompilerTest::pdata_escape1;
		$plogic   = KwartzCompilerTest::plogic_escape1;
		$expected = KwartzCompilerTest::expected_escape1_jstl11;
		$this->_test_escape($pdata, $plogic, $expected, 'jstl11');
	}

	function test_escape1_jstl10() {
		$pdata    = KwartzCompilerTest::pdata_escape1;
		$plogic   = KwartzCompilerTest::plogic_escape1;
		$expected = KwartzCompilerTest::expected_escape1_jstl10;
		$this->_test_escape($pdata, $plogic, $expected, 'jstl10');
	}



	const pdata_escape2 = '
		<li kd="Value:x">bar</li>
		<li kd="VALUE:x">baz</li>
		<li kd:php="Value($x)">bar</li>
		<li kd:php="VALUE($x)">baz</li>
		';
	
	const plogic_escape2 = '';
	
	const expected_escape2_php = '
		<li><?php echo htmlspecialchars($x); ?></li>
		<li><?php echo $x; ?></li>
		<li><?php echo htmlspecialchars($x); ?></li>
		<li><?php echo $x; ?></li>
		';

	const expected_escape2_eruby = '
		<li><%= CGI::escapeHTML((x).to_s) %></li>
		<li><%= x %></li>
		<li><%= CGI::escapeHTML((x).to_s) %></li>
		<li><%= x %></li>
		';

	const expected_escape2_jstl11 = '
		<li><c:out value="${x}"/></li>
		<li><c:out value="${x}" escapeXml="false"/></li>
		<li><c:out value="${x}"/></li>
		<li><c:out value="${x}" escapeXml="false"/></li>
		';

	const expected_escape2_jstl10 = '
		<li><c:out value="${x}"/></li>
		<li><c:out value="${x}" escapeXml="false"/></li>
		<li><c:out value="${x}"/></li>
		<li><c:out value="${x}" escapeXml="false"/></li>
		';

	function test_escape2_php() {
		$pdata    = KwartzCompilerTest::pdata_escape2;
		$plogic   = KwartzCompilerTest::plogic_escape2;
		$expected = KwartzCompilerTest::expected_escape2_php;
		$this->_test($pdata, $plogic, $expected, 'php');
		$this->_test_escape($pdata, $plogic, $expected, 'php');
	}

	function test_escape2_eruby() {
		$pdata    = KwartzCompilerTest::pdata_escape2;
		$plogic   = KwartzCompilerTest::plogic_escape2;
		$expected = KwartzCompilerTest::expected_escape2_eruby;
		$this->_test($pdata, $plogic, $expected, 'eruby');
		$this->_test_escape($pdata, $plogic, $expected, 'eruby');
	}

	function test_escape2_jstl11() {
		$pdata    = KwartzCompilerTest::pdata_escape2;
		$plogic   = KwartzCompilerTest::plogic_escape2;
		$expected = KwartzCompilerTest::expected_escape2_jstl11;
		$this->_test($pdata, $plogic, $expected, 'jstl11');
		$this->_test_escape($pdata, $plogic, $expected, 'jstl11');
	}

	function test_escape2_jstl10() {
		$pdata    = KwartzCompilerTest::pdata_escape2;
		$plogic   = KwartzCompilerTest::plogic_escape2;
		$expected = KwartzCompilerTest::expected_escape2_jstl10;
		$this->_test($pdata, $plogic, $expected, 'jstl10');
		$this->_test_escape($pdata, $plogic, $expected, 'jstl10');
	}

}


###
### execute test
###
//if ($argv[0] == 'KwartzCompilerTest.php') {
	$suite = new PHPUnit_TestSuite('KwartzCompilerTest');
	$result = PHPUnit::run($suite);
	//echo $result->toHTML();
	echo $result->toString();
//}
?>
