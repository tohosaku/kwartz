####
#### $Rev$
#### $Release$
#### $Copyright$
####


require 'kwartz/node'



module Kwartz


  ##
  ## [abstract] translate list of Statement into target code (eRuby, PHP, ...)
  ##
  class Translator


    ## [abstract] translate list of Statement into String and return it
    def translate(stmt_list)
      raise NotImplementedError.new("#{self.class}#translate() is not implemented.")
    end


    ## [abstract] translate NativeStatement using visitor pattern
    def translate_native_stmt(stmt)
      raise NotImplementedError.new("#{self.class}#translate_native_stmt() is not implemented.")
    end


    ## [abstract] translate PrintStatement using visitor pattern
    def translate_print_stmt(stmt)
      raise NotImplementedError.new("#{self.class}#translate_print_stmt() is not implemented.")
    end


    ## [abstract] translate NativeExpression using visitor pattern
    def translate_native_expr(expr)
      raise NotImplementedError.new("#{self.class}#translate_native_expr() is not implemented.")
    end


    ## [abstract] translate String using visitor pattern
    def translate_string(str)
      raise NotImplementedError.new("#{self.class}#translate_string() is not implemented.")
    end


#    ## [abstract] translate TextExpression using visitor pattern
#    def translate_text_expr(expr)
#      raise NotImplementedError.new("#{self.class}#translate_text_expr() is not implemented.")
#    end


  end #class



  ##
  ## concrete class for visitor pattern
  ##
  ## see ErbTranslator, PhpTranslator, JstlTranslator, and so on for detail.
  ##
  class BaseTranslator < Translator


    def initialize(marks=[], properties={})
      @stmt_l, @stmt_r, @expr_l, @expr_r, @escape_l, @escape_r = marks
      @nl = properties[:nl] || "\n"
      @escape = properties[:escape]
      @escape = Config::PROPERTY_ESCAPE if @escape == nil
      @header = properties[:header]
      @footer = properties[:footer]
    end


    attr_accessor :escape, :header, :footer


    def translate(stmt_list=[])
      @sb = ''
      @sb << @header if @header
      stmt_list.each do |stmt|
        stmt.accept(self)
      end
      @sb << @footer if @footer
      return @sb
    end


    def translate_native_stmt(stmt)
      @sb << @stmt_l << stmt.code << @stmt_r   # ex. <% stmt.code %>
      @sb << @nl unless stmt.no_newline
    end


    def translate_print_stmt(stmt)
      stmt.args.each do |arg|
        #arg.accept(self)
        if arg.is_a?(String)
          translate_string(arg)
        elsif arg.is_a?(NativeExpression)
          translate_native_expr(arg)
        else
          assert
        end
      end
    end


    def translate_native_expr(expr)
      assert unless expr.is_a?(NativeExpression)
      flag_escape = expr.escape?
      flag_escape = @escape if flag_escape == nil
      if flag_escape
        @sb << @escape_l << expr.code << @escape_r     # ex. <%=h expr.code %>
      else
        @sb << @expr_l << expr.code << @expr_r         # ex. <%= expr.code %>
      end
    end


    def translate_string(str)
      @sb << str
    end


#    def translate_text_expr(expr)
#      @sb << expr.text
#    end


  end #class



end #module
