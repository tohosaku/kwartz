###
### $Rev$
### $Release$
### $Copyright$
###


module Kwartz


  module Config


    PROPERTY_ESCAPE     = nil       # escape when true, not escape when false, handler depend when nil
    PROPERTY_ODD        = "'odd'"
    PROPERTY_EVEN       = "'even'"
    PROPERTY_LANG       = 'eruby'
    PROPERTY_DATTR      = 'kw:d'    # or 'title'
    PROPERTY_DELSPAN    = false     # delete dummy <span> tag or not
    PROPERTY_JSTL       = 1.2       # jstl version (1.2 or 1.1)
    #
    NO_ETAGS            = [ 'input', 'img' ,'br', 'hr', 'meta', 'link' ]
    ALLOW_DUPLICATE_ID  = false     # if true then enables to use duplicated id name.


  end


end
