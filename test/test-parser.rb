###
### $Rev$
### $Release$
### $Copyright$
###

require "#{File.dirname(__FILE__)}/test.rb"


class ParserTest < Test::Unit::TestCase

  ## define test methods
  filename = __FILE__.sub(/\.rb$/, '.yaml')
  load_yaml_testdata(filename, :lang=>'ruby')

  def _test
    begin
      eval @setup if @setup
      __test
    ensure
      eval @teardown if @teardown
    end
  end

  def __test
    case @style
    when 'ruby'
      parser = Kwartz::RubyStyleParser.new()
    when 'css'
      parser = Kwartz::CssStyleParser.new()
    else
      raise "*** invalid parser style: #{@style}"
    end
    if @name =~ /scan/
      actual = ''
      parser.__send__ :reset, @plogic
      while (ret = parser.scan()) != nil
        actual << "#{parser.linenum}:#{parser.column}:"
        actual << " token=#{parser.token.inspect}, value=#{parser.value.inspect}\n"
        break if ret == :error
      end
    else
      rulesets = parser.parse(@plogic)
      actual = ''
      rulesets.each do |ruleset|
        actual << ruleset._inspect()
      end if rulesets
    end
    assert_text_equal(@expected, actual)
  end

end
