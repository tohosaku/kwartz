###
### $Rev$
### $Release$
### $Copyright$
###


require 'kwartz'
require 'kwartz/binding/erb'
require 'kwartz/binding/php'
require 'kwartz/binding/eperl'
require 'kwartz/binding/rails'
require 'kwartz/binding/jstl'
require 'kwartz/binding/struts'
require 'kwartz/binding/erubis'



module Kwartz


  ##
  ## command option error class
  ##
  class CommandOptionError < KwartzError


    def initialize(message)
      super(message)
    end


  end



  ##
  ## main command
  ##
  ## ex.
  ##  Kwartz::Main.add_handler('mylang', MyLangDirectiveHandler, MyLangTranslator)
  ##  Kwartz::Main.main(ARGV)
  ##
  class Main


    def initialize(argv=ARGV)
      @argv = argv
      @command = File.basename($0)
    end


    def self.main(argv=ARGV)
      status = 0
      begin
        main = Kwartz::Main.new(argv)
        out = main.execute()
        print out unless out == nil
      rescue Kwartz::KwartzError => ex
        raise ex if $DEBUG
        $stderr.puts ex.to_s
        status = 1
      end
      exit status
    end


    def execute(argv=@argv)

      options, properties, filenames = parse_argv(argv, 'hveD', 'lkrpPx')
      options[?h] = true if properties[:help]

      if options[?h] || options[?v]
        puts version() if options[?v]
        puts help()    if options[?h]
        return nil
      end

      if filenames.empty?
        raise otpion_error("filename of presentation data is required.")
      end

      $KCODE = options[?k] if options[?k]
      $DEBUG = options[?D] if options[?D]

      style = options[?P] || 'css'
      parser_class = @@parser_table[style]
      parser_class     or raise option_error("-P #{style}: unknown style name (parser class not registered).")

      lang = options[?l] || Config::PROPERTY_LANG      # 'erb'
      handler_class = @@handler_table[lang]
      handler_class    or raise option_error("-l #{lang}: unknown name (handler class not registered).")

      translator_class = @@translator_table[lang]
      translator_class or raise option_error("-l #{lang}: unknown name (translator class not registered).")

      if options[?r]
        libraries = options[?r]
        libraries.split(/,/).each do |library|
          library.split!
          require library
        end
      end

      ruleset_list = []
      if options[?p]
        parser = parser_class.new(properties)
        options[?p].split(/,/).each do |filename|
          filename.strip!
          test(?f, filename)  or raise CommandOptionError.new("#{filename}: file not found.")
          plogic = File.read(filename)
          ruleset_list.concat(parser.parse(plogic))
        end
      end

      properties[:escape] = true if options[?e] && !properties.key?(:escape)

      properties[:handler] = handler_class.new
      stmt_list = []
      pdata = nil
      filenames.each do |filename|
        test(?f, filename)  or raise CommandOptionError.new("#{filename}: file not found.")
        pdata = File.read(filename)
        handler = handler_class.new(ruleset_list)
        converter = TextConverter.new(handler, properties)
        list = converter.convert(pdata)
        list = handler.extract(options[?x]) if options[?x]
        stmt_list.concat(list)
      end

      if options[?x]
        elem_id = options[?x]

      end

      if pdata[pdata.index(?\n) - 1] == ?\r
        properties[:nl] ||= "\r\n"
      end
      translator = translator_class.new(properties)
      output = translator.translate(stmt_list)
      return output

    end


    ## presentation logic parsers
    @@parser_table = {
      'css'    => CssStyleParser,
      'ruby'   => RubyStyleParser,
      #'yaml'   => YamlStylePLogicParser,
    }


    ## directive handlers
    @@handler_table = {
      'erb'    => ErbHandler,
      'php'    => PhpHandler,
      'eperl'  => EperlHandler,
      'rails'  => RailsHandler,
      'jstl'   => JstlHandler,
      'struts' => StrutsHandler,
      'erubis' => ErubisHandler,
    }


    ## translators
    @@translator_table = {
      'erb'    => ErbTranslator,
      'php'    => PhpTranslator,
      'eperl'  => EperlTranslator,
      'rails'  => RailsTranslator,
      'jstl'   => JstlTranslator,
      'struts' => StrutsTranslator,
      'erubis' => ErubisTranslator,
    }


    def self.add_parser_class(name, parser_class)
      @@parser_table[name] = parser_class
    end


    def self.get_parser_class(name)
      @@parser_table[name]
    end


    def self.add_handler_class(name, handler_class)
      @@handler_table[name] = handler_class
    end


    def self.get_handler_class(name)
      @@handler_table[name]
    end


    def self.add_translator_class(name, translator_class)
      @@translator_table[name] = translator_class
    end


    def self.get_translator_class(name)
      @@translator_table[name]
    end


    private


    def help
      s = ''
      s << "Usage: #{@command} [..options..] [-p plogic] file.html [file2.html ...]\n"
      s << "  -h             : help\n"
      s << "  -v             : version\n"
      #s << "  -D             : debug mode\n"
      s << "  -e             : alias of '--escape=true'\n"
      s << "  -l lang        : erb/php/eperl/rails/jstl (default 'erb')\n"
      s << "  -k kanji       : euc/sjis/utf8 (default nil)\n"
      s << "  -r library,... : require libraries\n"
      s << "  -p plogic,...  : presentation logic files\n"
      s << "  -x elem-id     : extract the element\n"
      #s << "  -P style       : style of presentation logic (css/ruby/yaml)\n"
      s << "  --dattr=str    : directive attribute name\n"
      s << "  --odd=value    : odd value for FOREACH/LOOP directive (default \"'odd'\")\n"
      s << "  --even=value   : even value for FOREACH/LOOP directive (default \"'even'\")\n"
      s << "  --header=str   : header text\n"
      s << "  --footer=str   : footer text\n"
      s << "  --escape={true|false} : escape (sanitize) (default false)\n"
      s << "  --jstl={1.2|1.1}      : JSTL version (default 1.2)\n"
      s << "  --charset=charset     : character set for JSTL (default none)\n"
      return s
    end


    def version
      v = ('$Release: 0.0.0$' =~ /[.\d]+/) && $&
      return v
    end


    def option_error(message)
      return CommandOptionError.new(message)
    end


    def parse_argv(argv=@argv, single_opts='', argument_opts='', optional_opts='')
      options = {}
      properties = {}

      while !argv.empty? && argv[0][0] == ?-

        optstr = argv.shift
        optstr = optstr[1, optstr.length - 1]

        if optstr[0] == ?-           # properties

          unless optstr =~ /\A-([-\w]+)(?:=(.*))?/
            raise option_error("'-#{optstr}': invalid property pattern.")
          end
          pname = $1 ;  pvalue = $2
          case pvalue
          when nil                    ;  pvalue = true
          when /\A\d+\z/              ;  pvalue = pvalue.to_i
          when /\A\d+\.\d+\z/         ;  pvalue = pvalue.to_f
          when 'true', 'yes', 'on'    ;  pvalue = true
          when 'false', 'no', 'off'   ;  pvalue = false
          when 'nil', 'null'          ;  pvalue = nil
          when /\A'.*'\z/, /\A".*"\z/ ; pvalue = eval pvalue
          end
          properties[pname.intern] = pvalue

        else                         # command-line options

          while optstr && !optstr.empty?
            optchar = optstr[0]
            optstr = optstr[1, optstr.length - 1]
            if single_opts && single_opts.include?(optchar)
              options[optchar] = true
            elsif argument_opts && argument_opts.include?(optchar)
              arg = optstr
              arg = argv.shift unless arg && !arg.empty?
              unless arg
                raise option_error("-#{optchar.chr}: argument required.")
              end
              options[optchar] = arg
              optstr = ''
            elsif optional_opts && optional_opts.include?(optchar)
              arg = optstr
              arg = true unless arg && !arg.empty?
              options[optchar] = arg
              optstr = ''
            end
          end #while

        end #if

      end #while

      filenames = argv
      return options, properties, filenames

    end #def


  end #class



end #module



if $0 == __FILE__

  Kwartz::Main.main(ARGV)

end
