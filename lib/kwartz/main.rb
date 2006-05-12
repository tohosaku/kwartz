###
### $Rev$
### $Release$
### $Copyright$
###


require 'kwartz'
require 'kwartz/binding/eruby'
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

      options, properties, filenames = parse_argv(argv, 'hveD', 'lkrpPxXi')
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
      parser_class = PresentationLogicParser.get_class(style)
      parser_class     or raise option_error("-P #{style}: unknown style name (parser class not registered).")

      lang = options[?l] || Config::PROPERTY_LANG      # 'eruby'
      handler_class = Handler.get_class(lang)
      handler_class    or raise option_error("-l #{lang}: unknown name (handler class not registered).")

      translator_class = Translator.get_class(lang)
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

      handler = handler_class.new(ruleset_list, properties)
      converter = TextConverter.new(handler, properties)

      if options[?i]
        import_filenames = options[?i].split(/,/)
        import_filenames.each do |filename|
          test(?f, filename)  or raise CommandOptionError.new("-i #{filename}: file not found.")
          pdata = File.read(filename)
          converter.convert(pdata)
        end
      end

      stmt_list = []
      pdata = nil
      filenames.each do |filename|
        test(?f, filename)  or raise CommandOptionError.new("#{filename}: file not found.")
        pdata = File.read(filename)
        #handler = handler_class.new(ruleset_list)
        #converter = TextConverter.new(handler, properties)
        list = converter.convert(pdata)
        stmt_list.concat(list)
      end

      elem_id = options[?x] || options[?X]
      if elem_id
        content_only = options[?x] ? true : false
        stmt_list = handler.extract(elem_id, content_only)
      end

      if pdata[pdata.index(?\n) - 1] == ?\r
        properties[:nl] ||= "\r\n"
      end
      translator = translator_class.new(properties)
      output = translator.translate(stmt_list)
      return output

    end


    private


    def help
      sb = []
      sb << "Usage: #{@command} [..options..] [-p plogic] file.html [file2.html ...]\n"
      sb << "  -h             : help\n"
      sb << "  -v             : version\n"
      #sb << "  -D             : debug mode\n"
      sb << "  -e             : alias of '--escape=true'\n"
      sb << "  -l lang        : eruby/php/eperl/rails/jstl (default 'eruby')\n"
      sb << "  -k kanji       : euc/sjis/utf8 (default nil)\n"
      sb << "  -r library,... : require libraries\n"
      sb << "  -p plogic,...  : presentation logic files\n"
      sb << "  -x elem-id     : extract content of element marked by elem-id\n"
      sb << "  -X elem-id     : extract element marked by elem-id\n"
      #sb << "  -P style       : style of presentation logic (css/ruby/yaml)\n"
      sb << "  --dattr=str    : directive attribute name\n"
      sb << "  --odd=value    : odd value for FOREACH/LOOP directive (default \"'odd'\")\n"
      sb << "  --even=value   : even value for FOREACH/LOOP directive (default \"'even'\")\n"
      sb << "  --header=str   : header text\n"
      sb << "  --footer=str   : footer text\n"
      sb << "  --delspan={true|false}: delete dummy span tag (default false)\n"
      sb << "  --escape={true|false} : escape (sanitize) (default false)\n"
      sb << "  --jstl={1.2|1.1}      : JSTL version (default 1.2)\n"
      sb << "  --charset=charset     : character set for JSTL (default none)\n"
      return sb.join
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
            else
              raise CommandOptionError.new("-#{optchar.chr}: invalid command option.")
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
