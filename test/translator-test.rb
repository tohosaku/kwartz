#!/usr/bin/ruby

###
### unit test for Translator
###
### $Id$
###

$: << 'lib'
$: << '../lib'
$: << 'test'

require 'test/unit'
require 'test/unit/ui/console/testrunner'
require 'assert-diff.rb'
require 'kwartz/parser'
require 'kwartz/translator'
require 'kwartz/translator/eruby'
require 'kwartz/translator/php'


##
## translator test
##
class TranslatorTest < Test::Unit::TestCase

   def setup
      @flag_suspend = false
   end

   def _test(method_name, input, expected, properties={})
      s = caller()[1]
      s =~ /in `(.*)'/          #'
      testmethod = $1
      case testmethod
      when /_eruby$/
         lang = 'eruby'
      when /_php$/
         lang = 'php'
      else
         raise "invalid testmethod name (='#{testmethod}')"
      end
      parser = Kwartz::Parser.new(input, properties)
      block_stmt = parser.__send__(method_name)
      translator = Kwartz::Translator.create(lang, properties)
      actual = translator.translate(block_stmt)
      assert_equal_with_diff(expected, actual)
   end

   def _test_expr(input, expected, properties={})
      _test('parse_expression', input, expected, properties)
   end

   def _test_stmt(input, expected, properties={})
      _test('parse_program', input, expected, properties)
   end



   ## ======================================== expression

   ## ---------------------------- unary, binary

   @@expression1 = 'a + b * c - d % e'
   def test_expression1_eruby
      expected = 'a + b * c - d % e'
      _test_expr(@@expression1, expected)
   end
   def test_expression1_php
      expected = '$a + $b * $c - $d % $e'
      _test_expr(@@expression1, expected)
   end


   @@expression2 = 'a * (b+c) % (d.+e)'
   def test_expression2_eruby
      expected = 'a * (b + c) % (d + e)'
      _test_expr(@@expression2, expected)
   end
   def test_expression2_php
      expected = '$a * ($b + $c) % ($d . $e)'
      _test_expr(@@expression2, expected)
   end


   @@expression3 = '- 2 * b'
   def test_expression3_eruby
      expected = '-2 * b'
      _test_expr(@@expression3, expected)
   end
   def test_expression3_php
      expected = '-2 * $b'
      _test_expr(@@expression3, expected)
   end


   ## ---------------------------- assignment

   @@assign1 = 'a = 10'
   def test_assign1_eruby
      expected = 'a = 10'
      _test_expr(@@assign1, expected)
   end
   def test_assign1_php
      expected = '$a = 10'
      _test_expr(@@assign1, expected)
   end


   @@assign2 = 'a += i+1'
   def test_assign2_eruby
      expected = 'a += i + 1'
      _test_expr(@@assign2, expected)
   end
   def test_assign2_php
      expected = '$a += $i + 1'
      _test_expr(@@assign2, expected)
   end


   @@assign3 = 'a[i] *= a[i-2]+a[i-1]'
   def test_assign3_eruby
      expected = 'a[i] *= a[i - 2] + a[i - 1]'
      _test_expr(@@assign3, expected)
   end
   def test_assign3_php
      expected = '$a[$i] *= $a[$i - 2] + $a[$i - 1]'
      _test_expr(@@assign3, expected)
   end


   @@assign4 = "a[:name] .+= 's1'.+'s2'"
   def test_assign4_eruby
      expected = 'a[:name] += "s1" + "s2"'
      _test_expr(@@assign4, expected)
   end
   def test_assign4_php
      expected = "$a['name'] .= \"s1\" . \"s2\""
      _test_expr(@@assign4, expected)
   end


   ## ---------------------------- function

   @@function1 = 'list = list_new()'
   def test_function1_eruby
      expected = 'list = []'
      _test_expr(@@function1, expected)
   end
   def test_function1_php
      expected = '$list = array()'
      _test_expr(@@function1, expected)
   end


   @@function2 = 'hash = hash_new()'
   def test_function2_eruby
      expected = 'hash = {}'
      _test_expr(@@function2, expected)
   end
   def test_function2_php
      expected = '$hash = array()'
      _test_expr(@@function2, expected)
   end


   @@function3 = 'len = list_length(list) + str_length(str)'
   def test_function3_eruby
      expected = 'len = list.length + str.length'
      _test_expr(@@function3, expected)
   end
   def test_function3_php
      expected = '$len = count($list) + strlen($str)'
      _test_expr(@@function3, expected)
   end


   @@function4 = 'flag = list_empty(list) && hash_empty(hash) && str_empty(str)'
   def test_function4_eruby
      expected = 'flag = list.empty? && hash.empty? && str.empty?'
      _test_expr(@@function4, expected)
   end
   def test_function4_php
      expected = '$flag = count($list)==0 && count($hash)==0 && $str'
      _test_expr(@@function4, expected)
   end


   @@function5 = 'str_trim(s) .+ str_toupper(s) .+ str_tolower(s) .+ str_index(s, "x")'
   def test_function5_eruby
      expected = 's.trim + s.upcase + s.downcase + s.index("x")'
      _test_expr(@@function5, expected)
   end
   def test_function5_php
      expected = 'trim($s) . strtoupper($s) . strtolower($s) . strstr($s, "x")'
      _test_expr(@@function5, expected)
   end


   @@function6 = 'list_length(hash_keys(hash))'
   def test_function6_eruby
      expected = 'hash.keys.length'
      _test_expr(@@function6, expected)
   end
   def test_function6_php
      expected = 'count(array_keys($hash))'
      _test_expr(@@function6, expected)
   end


   ## ---------------------------- conditional op

   @@conditional1 = 'max = x > y ? x : y'
   def test_conditional1_eruby
      expected = 'max = x > y ? x : y'
      _test_expr(@@conditional1, expected)
   end
   def test_conditional1_php
      expected = '$max = $x > $y ? $x : $y'
      _test_expr(@@conditional1, expected)
   end


   @@conditional2 = 'klass = (i+=1)%2==0?"#FFCCCC":"#CCCCFF"'
   def test_conditional2_eruby
      expected = 'klass = (i += 1) % 2 == 0 ? "#FFCCCC" : "#CCCCFF"'
      _test_expr(@@conditional2, expected)
   end
   def test_conditional2_php
      expected = '$klass = ($i += 1) % 2 == 0 ? "#FFCCCC" : "#CCCCFF"'
      _test_expr(@@conditional2, expected)
   end



   ## ======================================== statement

   ## ---------------------------- expression statement

   @@expr_stmt1 = "a = 1;"
   def test_expr_stmt1_eruby
      expected = "<% a = 1 %>\n"
      _test_stmt(@@expr_stmt1, expected)
   end
   def test_expr_stmt1_php
      expected = "<?php $a = 1; ?>\n"
      _test_stmt(@@expr_stmt1, expected)
   end


   @@epxr_stmt2 = "a[i] += x>y ? x : y;"
   def test_expr_stmt2_eruby
      expected = "<% a[i] += x > y ? x : y %>\n"
      _test_stmt(@@epxr_stmt2, expected)
   end
   def test_expr_stmt2_php
      expected = "<?php $a[$i] += $x > $y ? $x : $y; ?>\n"
      _test_stmt(@@epxr_stmt2, expected)
   end


   ## ---------------------------- print statement

   @@print_stmt1 = 'print("foo", a+b, "\n");'
   def test_print_stmt1_eruby
      expected = "foo<%= a + b %>\n"
      _test_stmt(@@print_stmt1, expected)
   end
   def test_print_stmt1_php
      expected = "foo<?php echo $a + $b; ?>\n"
      _test_stmt(@@print_stmt1, expected)
   end


   @@print_stmt2 = 'print(E(e), X(x), default);'
   def test_print_stmt2_eruby
      expected = "<%= CGI::escapeHTML((e).to_s) %><%= x %><%= default %>"
      _test_stmt(@@print_stmt2, expected)
   end
   def test_print_stmt2_php
      expected = "<?php echo htmlspecialchars($e); ?><?php echo $x; ?><?php echo $default; ?>"
      _test_stmt(@@print_stmt2, expected)
   end


   @@print_stmt3 = 'print(E(e), X(x), default);'
   def test_print_stmt3_eruby
      expected = "<%= CGI::escapeHTML((e).to_s) %><%= x %><%= CGI::escapeHTML((default).to_s) %>"
      _test_stmt(@@print_stmt3, expected, {:escape=>true})
   end
   def test_print_stmt3_php
      expected = "<?php echo htmlspecialchars($e); ?><?php echo $x; ?><?php echo htmlspecialchars($default); ?>"
      _test_stmt(@@print_stmt3, expected, {:escape=>true})
   end



   ## ---------------------------- if statement

   @@if_stmt1 = 'if (x>y) print(x); else print(y);'
   def test_if_stmt1_eruby
      expected = <<'END'
<% if x > y then %>
<%= x %><% else %>
<%= y %><% end %>
END
      _test_stmt(@@if_stmt1, expected)
   end
   def test_if_stmt1_php
      expected = <<'END'
<?php if ($x > $y) { ?>
<?php echo $x; ?><?php } else { ?>
<?php echo $y; ?><?php } ?>
END
      _test_stmt(@@if_stmt1, expected)
   end


   @@if_stmt2 = 'if (x>y) print(x); else if (y>z) print(y);'
   def test_if_stmt2_eruby
      expected = <<'END'
<% if x > y then %>
<%= x %><% elsif y > z then %>
<%= y %><% end %>
END
      _test_stmt(@@if_stmt2, expected)
   end
   def test_if_stmt2_php
      expected = <<'END'
<?php if ($x > $y) { ?>
<?php echo $x; ?><?php } elseif ($y > $z) { ?>
<?php echo $y; ?><?php } ?>
END
      _test_stmt(@@if_stmt2, expected)
   end


   @@if_stmt3 = <<'END'
if (x>y && x>z) {
  max = x;
} else if (y>x && y>z) {
  max = y;
} else if (z>x && z>x) {
  max = z;
} else {
  max = -1;
}
END
   def test_if_stmt3_eruby
      expected = <<'END'
<% if x > y && x > z then %>
<%   max = x %>
<% elsif y > x && y > z then %>
<%   max = y %>
<% elsif z > x && z > x then %>
<%   max = z %>
<% else %>
<%   max = -1 %>
<% end %>
END
      _test_stmt(@@if_stmt3, expected)
   end
   def test_if_stmt3_php
      expected = <<'END'
<?php if ($x > $y && $x > $z) { ?>
<?php   $max = $x; ?>
<?php } elseif ($y > $x && $y > $z) { ?>
<?php   $max = $y; ?>
<?php } elseif ($z > $x && $z > $x) { ?>
<?php   $max = $z; ?>
<?php } else { ?>
<?php   $max = -1; ?>
<?php } ?>
END
      _test_stmt(@@if_stmt3, expected)
   end


   ## ---------------------------- foreach statement

   @@foreach_stmt1 = <<'END'
foreach (item in list)
  print("<li>", item, "</li>\n");
END
   def test_foreach_stmt1_eruby
      expected = <<'END'
<% for item in list do %>
<li><%= item %></li>
<% end %>
END
      _test_stmt(@@foreach_stmt2, expected)
   end
   def test_foreach_stmt1_php
      expected = <<'END'
<?php foreach ($list as $item) { ?>
<li><?php echo $item; ?></li>
<?php } ?>
END
      _test_stmt(@@foreach_stmt2, expected)
   end


   @@foreach_stmt2 = <<'END'
foreach (item in list) {
  print("<li>", item, "</li>\n");
}
END
   def test_foreach_stmt2_eruby
      expected = <<'END'
<% for item in list do %>
<li><%= item %></li>
<% end %>
END
      _test_stmt(@@foreach_stmt2, expected)
   end
   def test_foreach_stmt2_php
      expected = <<'END'
<?php foreach ($list as $item) { ?>
<li><?php echo $item; ?></li>
<?php } ?>
END
      _test_stmt(@@foreach_stmt2, expected)
   end


   ## ---------------------------- while statement


   @@while_stmt1 = <<'END'
while (i<len) i += i;
END
   def test_while_stmt1_eruby
      expected = <<'END'
<% while i < len do %>
<%   i += i %>
<% end %>
END
      _test_stmt(@@while_stmt1, expected)
   end
   def test_while_stmt1_php
      expected = <<'END'
<?php while ($i < $len) { ?>
<?php   $i += $i; ?>
<?php } ?>
END
      _test_stmt(@@while_stmt1, expected)
   end


   ## ---------------------------- expand statement

   @@expand_stmt1 = '@element(foo);'
   def test_expand_stmt1_eruby
      expected = ''
      assert_raise(Kwartz::TranslationError) do
         _test_stmt(@@expand_stmt1, expected)
      end
   end
   def test_expand_stmt1_php
      expected = ''
      assert_raise(Kwartz::TranslationError) do
         _test_stmt(@@expand_stmt1, expected)
      end
   end


end


##
## main
##
if $0 == __FILE__
    Test::Unit::UI::Console::TestRunner.run(TranslatorTest)
end